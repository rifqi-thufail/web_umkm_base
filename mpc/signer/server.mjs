// HTTP bridge between Laravel and the Mpcium MPC cluster.
//
// Laravel cannot speak NATS, so this process holds the event-initiator key and relays requests:
//   POST /v1/keygen/prepare { wallet_id }                          -> { initiator_signature }
//   POST /v1/keygen         { wallet_id, authorizer_signature }    -> { wallet_id, address, public_key }
//   POST /v1/tx/prepare     { wallet_id, tx_id, from, to, value_wei } -> { tx, unsigned_hash, initiator_signature }
//   POST /v1/tx/submit      { wallet_id, tx_id, from, tx, authorizer_signature } -> { tx_hash }
//   GET  /v1/tx/:tx_id      -> cached submit outcome (for reconciliation)
//   GET  /health            (unauthenticated, no details)
//
// The nodes are configured to require a second signature from Laravel's authorizer key on every
// request. Laravel rebuilds each transaction itself and only co-signs what its own policy allows, so
// a compromise of this process (its HTTP token, initiator key or NATS credentials) cannot move funds.
// No private key share ever exists here: the nodes run threshold ECDSA and only return (r, s, v).
import crypto from 'node:crypto';
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { ethers } from 'ethers';
import { AckPolicy, connect, headers as natsHeaders, JSONCodec } from 'nats';
import {
    TX_ID_RE,
    WALLET_ID_RE,
    addressFromPubKey,
    authorizerRaw,
    buildTransfer,
    ed25519KeyFromHex,
    ed25519Sign,
    keygenRaw,
    networkCode,
    signTxRaw,
    transferFields,
    GAS_LIMIT,
} from './protocol.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const env = process.env;

const PORT = Number(env.MPC_SIGNER_PORT || 7077);
const HOST = env.MPC_SIGNER_HOST || '127.0.0.1';
const TOKEN = env.MPC_SIGNER_TOKEN || '';
const RPC_URL = env.BASE_RPC_URL || 'http://127.0.0.1:8545';
const CHAIN_ID = BigInt(env.BASE_CHAIN_ID || 84532);
const CLIENT_ID = env.MPC_CLIENT_ID || 'ampuh';
const AUTHORIZER_ID = env.MPC_AUTHORIZER_ID || 'ampuh-laravel';
const KEY_PATH = env.MPC_INITIATOR_KEY_PATH || path.join(here, '..', 'cluster', 'event_initiator.key');
const TIMEOUT_MS = Number(env.MPC_TIMEOUT_MS || 90_000);
const MAX_BODY = 16 * 1024;
const RESULT_STREAM = 'mpc';

function fatal(msg) {
    console.error(`[signer] ${msg}`);
    process.exit(1);
}

if (TOKEN.length < 32) fatal('MPC_SIGNER_TOKEN must be set to at least 32 random characters (openssl rand -hex 32)');
if (!/^[A-Za-z0-9_-]+$/.test(CLIENT_ID)) fatal('MPC_CLIENT_ID must be a single NATS subject token');

const initiatorKey = loadInitiatorKey(KEY_PATH);
const tokenDigest = sha256(TOKEN);

function loadInitiatorKey(file) {
    const stat = fs.statSync(file);
    if (process.platform !== 'win32' && stat.mode & 0o077) {
        fatal(`${file} is readable by other users; run: chmod 600 ${file}`);
    }
    return ed25519KeyFromHex(fs.readFileSync(file, 'utf8'));
}

function sha256(s) {
    return crypto.createHash('sha256').update(s).digest();
}

// --- NATS -------------------------------------------------------------------------------------

const tls =
    env.MPC_NATS_CA_FILE || env.MPC_NATS_CERT_FILE
        ? { caFile: env.MPC_NATS_CA_FILE, certFile: env.MPC_NATS_CERT_FILE, keyFile: env.MPC_NATS_KEY_FILE }
        : undefined;

const nc = await connect({
    servers: env.MPC_NATS_URL || 'nats://127.0.0.1:4222',
    user: env.MPC_NATS_USER || undefined,
    pass: env.MPC_NATS_PASSWORD || undefined,
    tls,
    name: `ampuh-signer-${CLIENT_ID}`,
    maxReconnectAttempts: -1,
    inboxPrefix: `_INBOX_${CLIENT_ID}`,
});
const js = nc.jetstream();
const jc = JSONCodec();
const provider = new ethers.JsonRpcProvider(RPC_URL, undefined, { staticNetwork: true });

(async () => {
    for await (const s of nc.status()) console.warn(`[signer] nats ${s.type}`);
})();

// Results arrive asynchronously on JetStream; match them to waiting requests by id.
// A result can in theory land before the waiter registers, so unclaimed results are parked briefly.
const waiters = { keygen: new Map(), sign: new Map() };
const early = { keygen: new Map(), sign: new Map() };
const EARLY_MAX = 1000;

function waitFor(kind, id) {
    return new Promise((resolve, reject) => {
        const parked = early[kind].get(id);
        if (parked) {
            early[kind].delete(id);
            return resolve(parked);
        }
        const timer = setTimeout(() => {
            waiters[kind].delete(id);
            reject(httpError(504, `MPC ${kind} ${id} timed out — are enough nodes running?`, { broadcast: false }));
        }, TIMEOUT_MS);
        waiters[kind].set(id, { resolve, timer });
    });
}

function deliver(kind, id, event) {
    const w = waiters[kind].get(id);
    if (!w) {
        if (early[kind].size >= EARLY_MAX) early[kind].delete(early[kind].keys().next().value);
        early[kind].set(id, event);
        setTimeout(() => early[kind].delete(id), TIMEOUT_MS).unref();
        return;
    }
    waiters[kind].delete(id);
    clearTimeout(w.timer);
    w.resolve(event);
}

// Our results go to client-scoped subjects (ClientID header), so they never mix with other clients'.
const RESULT_CONSUMERS = [
    { kind: 'keygen', durable: `${CLIENT_ID}_keygen_result`, subject: `mpc.mpc_keygen_result.${CLIENT_ID}.*`, id: (e) => e.wallet_id },
    { kind: 'sign', durable: `${CLIENT_ID}_signing_result`, subject: `mpc.mpc_signing_result.${CLIENT_ID}.complete`, id: (e) => e.tx_id },
];

async function consumeResults({ kind, durable, subject, id }) {
    const jsm = await nc.jetstreamManager();
    // The nodes create the result stream on startup; wait for it rather than creating it ourselves
    // (the signer's NATS user is deliberately not allowed to create or modify streams).
    for (;;) {
        try {
            await jsm.consumers.info(RESULT_STREAM, durable);
            break;
        } catch {
            try {
                await jsm.consumers.add(RESULT_STREAM, {
                    durable_name: durable,
                    filter_subject: subject,
                    ack_policy: AckPolicy.Explicit,
                    ack_wait: 30_000_000_000,
                    max_deliver: 3,
                });
                break;
            } catch (err) {
                console.warn(`[signer] waiting for result stream (${err.message})`);
                await new Promise((r) => setTimeout(r, 2000));
            }
        }
    }
    const consumer = await js.consumers.get(RESULT_STREAM, durable);
    const messages = await consumer.consume();
    console.log(`[signer] consuming ${kind} results on ${subject}`);
    for await (const m of messages) {
        try {
            const event = jc.decode(m.data);
            deliver(kind, id(event), event);
            m.ack();
        } catch (err) {
            console.error(`[signer] bad ${kind} result:`, err.message);
            m.term();
        }
    }
}

for (const c of RESULT_CONSUMERS) {
    consumeResults(c).catch((err) => fatal(`result consumer ${c.durable} stopped: ${err.message}`));
}

async function publish(subject, msg) {
    const h = natsHeaders();
    h.set('ClientID', CLIENT_ID);
    await js.publish(subject, jc.encode(msg), { headers: h });
}

// --- Handlers ---------------------------------------------------------------------------------

function requireWalletId(v) {
    if (typeof v !== 'string' || !WALLET_ID_RE.test(v)) throw httpError(422, 'invalid wallet_id');
    return v;
}

function requireTxId(v) {
    if (typeof v !== 'string' || !TX_ID_RE.test(v)) throw httpError(422, 'tx_id must be a lowercase UUID');
    return v;
}

function requireAddress(v, field) {
    if (typeof v !== 'string' || !ethers.isAddress(v)) throw httpError(422, `${field} must be an address`);
    return ethers.getAddress(v);
}

function requireSignature(v) {
    const buf = typeof v === 'string' ? Buffer.from(v, 'base64') : null;
    if (!buf || buf.length !== 64 || buf.toString('base64') !== v) throw httpError(422, 'authorizer_signature must be a base64 ed25519 signature');
    return buf;
}

function keygenPrepare({ wallet_id }) {
    const walletId = requireWalletId(wallet_id);
    return { wallet_id: walletId, initiator_signature: ed25519Sign(initiatorKey, keygenRaw(walletId)).toString('base64') };
}

const inflightKeygen = new Map();

async function keygen({ wallet_id, authorizer_signature }) {
    const walletId = requireWalletId(wallet_id);
    const authSig = requireSignature(authorizer_signature);
    if (inflightKeygen.has(walletId)) return inflightKeygen.get(walletId);

    const run = (async () => {
        const raw = keygenRaw(walletId);
        const sig = ed25519Sign(initiatorKey, raw);
        const result = waitFor('keygen', walletId);
        // Mpcium replays the stored result for an existing wallet id, so retries are safe.
        await publish(`mpc.keygen_request.${walletId}`, {
            wallet_id: walletId,
            signature: sig.toString('base64'),
            authorizer_signatures: [{ authorizer_id: AUTHORIZER_ID, signature: authSig.toString('base64') }],
        });
        const event = await result;
        if (event.result_type === 'error') throw httpError(502, `keygen failed: ${event.error_reason || event.error_code}`);
        if (!event.ecdsa_pub_key) throw httpError(502, 'keygen result has no ECDSA key');
        const pub = Buffer.from(event.ecdsa_pub_key, 'base64');
        return { wallet_id: walletId, address: addressFromPubKey(pub), public_key: pub.toString('hex') };
    })();

    inflightKeygen.set(walletId, run);
    return run.finally(() => inflightKeygen.delete(walletId));
}

// Asked fresh on every request (the provider's own network is cached), so a swapped or
// misconfigured RPC endpoint is caught before anything is signed or broadcast.
async function chainIdCheck() {
    const chainId = BigInt(await provider.send('eth_chainId', []));
    if (chainId !== CHAIN_ID) {
        throw httpError(503, `RPC is on chain ${chainId}, expected ${CHAIN_ID}`, { broadcast: false });
    }
}

async function txPrepare({ wallet_id, tx_id, from, to, value_wei }) {
    const walletId = requireWalletId(wallet_id);
    const txId = requireTxId(tx_id);
    const sender = requireAddress(from, 'from');
    const recipient = requireAddress(to, 'to');
    if (!/^[1-9]\d{0,77}$/.test(String(value_wei))) throw httpError(422, 'value_wei must be a positive integer string');

    await chainIdCheck();
    const [nonce, fees, balance] = await Promise.all([
        provider.getTransactionCount(sender, 'pending'),
        provider.getFeeData(),
        provider.getBalance(sender, 'pending'),
    ]);
    if (!fees.maxFeePerGas) throw httpError(503, 'RPC does not report EIP-1559 fees');

    const tx = buildTransfer({
        chain_id: CHAIN_ID.toString(),
        nonce: String(nonce),
        max_fee_per_gas: fees.maxFeePerGas.toString(),
        max_priority_fee_per_gas: (fees.maxPriorityFeePerGas ?? 0n).toString(),
        gas_limit: GAS_LIMIT.toString(),
        to: recipient,
        value: String(value_wei),
    });

    const cost = tx.value + tx.gasLimit * tx.maxFeePerGas;
    if (balance < cost) throw httpError(422, `insufficient funds: need ${ethers.formatEther(cost)} ETH incl. gas`, { code: 'insufficient_funds' });

    const txHash = ethers.getBytes(tx.unsignedHash);
    const raw = signTxRaw({ walletId, networkInternalCode: networkCode(CHAIN_ID), txId, txHash });
    return {
        tx: transferFields(tx),
        unsigned_hash: tx.unsignedHash,
        initiator_signature: ed25519Sign(initiatorKey, raw).toString('base64'),
    };
}

// Submit outcomes by tx_id, so a retried submit (e.g. after a client timeout) returns the same
// answer instead of asking the nodes to sign again. Bounded, in-memory; reconciliation also
// checks the chain itself.
const submits = new Map();
const SUBMITS_MAX = 10_000;
const SUBMIT_TTL_MS = 24 * 60 * 60 * 1000;

function remember(txId, promise) {
    if (submits.size >= SUBMITS_MAX) submits.delete(submits.keys().next().value);
    const entry = { promise, at: Date.now(), outcome: null };
    submits.set(txId, entry);
    promise.then(
        (v) => (entry.outcome = { status: 'broadcast', ...v }),
        (e) => (entry.outcome = { status: e.broadcast === false ? 'failed' : 'unknown', error: e.message }),
    );
    return promise;
}

async function txSubmit({ wallet_id, tx_id, from, tx: fields, authorizer_signature }) {
    const walletId = requireWalletId(wallet_id);
    const txId = requireTxId(tx_id);
    const sender = requireAddress(from, 'from');
    const authSig = requireSignature(authorizer_signature);

    const cached = submits.get(txId);
    if (cached && Date.now() - cached.at < SUBMIT_TTL_MS) return cached.promise;

    const tx = buildTransfer(fields);
    if (tx.chainId !== CHAIN_ID) throw httpError(422, 'tx.chain_id does not match this signer', { broadcast: false });

    return remember(
        txId,
        (async () => {
            await chainIdCheck();
            const txHash = ethers.getBytes(tx.unsignedHash);
            const msg = {
                key_type: 'secp256k1',
                wallet_id: walletId,
                network_internal_code: networkCode(CHAIN_ID),
                tx_id: txId,
                tx: Buffer.from(txHash).toString('base64'),
            };
            const sig = ed25519Sign(initiatorKey, signTxRaw({ walletId, networkInternalCode: msg.network_internal_code, txId, txHash }));

            const result = waitFor('sign', txId);
            await publish(`mpc.signing_request.${txId}`, {
                ...msg,
                signature: sig.toString('base64'),
                authorizer_signatures: [{ authorizer_id: AUTHORIZER_ID, signature: authSig.toString('base64') }],
            });
            const event = await result;
            if (event.result_type !== 'success') {
                throw httpError(502, `signing failed: ${event.error_reason || event.error_code || 'unknown'}`, { broadcast: false });
            }

            tx.signature = {
                r: '0x' + Buffer.from(event.r, 'base64').toString('hex'),
                s: '0x' + Buffer.from(event.s, 'base64').toString('hex'),
                v: Buffer.from(event.signature_recovery, 'base64')[0],
            };
            if (tx.from !== sender) {
                throw httpError(502, `signature recovers to ${tx.from}, expected ${sender}`, { broadcast: false });
            }

            const hash = tx.hash;
            try {
                await provider.broadcastTransaction(tx.serialized);
            } catch (err) {
                // Already in the mempool (e.g. a retried broadcast) is success; anything else is ambiguous
                // enough that Laravel must reconcile against the chain rather than assume failure.
                if (!/already known|known transaction/i.test(err.message)) {
                    throw httpError(502, `broadcast failed: ${err.shortMessage || err.message}`, { tx_hash: hash });
                }
            }
            return { tx_hash: hash };
        })(),
    );
}

function txStatus(txId) {
    requireTxId(txId);
    const entry = submits.get(txId);
    if (!entry) throw httpError(404, 'unknown tx_id');
    return entry.outcome ?? { status: 'in_progress' };
}

// --- HTTP -------------------------------------------------------------------------------------

function httpError(status, message, extra = {}) {
    return Object.assign(new Error(message), { status, ...extra });
}

function authorized(req) {
    const header = req.headers.authorization || '';
    return header.startsWith('Bearer ') && crypto.timingSafeEqual(sha256(header.slice(7)), tokenDigest);
}

async function readJson(req) {
    if (!/^application\/json\b/.test(req.headers['content-type'] || '')) throw httpError(415, 'expected application/json');
    let size = 0;
    const chunks = [];
    for await (const chunk of req) {
        size += chunk.length;
        if (size > MAX_BODY) throw httpError(413, 'request body too large');
        chunks.push(chunk);
    }
    try {
        const body = JSON.parse(Buffer.concat(chunks).toString('utf8'));
        if (!body || typeof body !== 'object' || Array.isArray(body)) throw new Error();
        return body;
    } catch {
        throw httpError(400, 'invalid JSON body');
    }
}

const routes = {
    'POST /v1/keygen/prepare': keygenPrepare,
    'POST /v1/keygen': keygen,
    'POST /v1/tx/prepare': txPrepare,
    'POST /v1/tx/submit': txSubmit,
};

const server = http.createServer(async (req, res) => {
    const reply = (status, body) => {
        res.writeHead(status, { 'content-type': 'application/json', 'cache-control': 'no-store' });
        res.end(JSON.stringify(body));
    };
    const url = new URL(req.url, 'http://signer');
    try {
        if (req.method === 'GET' && url.pathname === '/health') {
            return reply(nc.isClosed() ? 503 : 200, { ok: !nc.isClosed() });
        }
        if (!authorized(req)) return reply(401, { error: 'unauthorized' });

        const txMatch = req.method === 'GET' && url.pathname.match(/^\/v1\/tx\/([^/]+)$/);
        if (txMatch) return reply(200, txStatus(txMatch[1]));

        const handler = routes[`${req.method} ${url.pathname}`];
        if (!handler) return reply(404, { error: 'not found' });
        reply(200, await handler(await readJson(req)));
    } catch (err) {
        const status = err.status || 500;
        console.error(`[signer] ${req.method} ${url.pathname} -> ${status}: ${err.message}`);
        const body = { error: status === 500 ? 'internal error' : err.message };
        if (err.code) body.code = err.code;
        if (err.broadcast === false) body.broadcast = false;
        if (err.tx_hash) body.tx_hash = err.tx_hash;
        reply(status, body);
    }
});

server.requestTimeout = TIMEOUT_MS + 30_000;
server.headersTimeout = 10_000;
server.listen(PORT, HOST, () => console.log(`[signer] listening on http://${HOST}:${PORT} (rpc ${new URL(RPC_URL).host}, chain ${CHAIN_ID})`));

for (const s of ['SIGINT', 'SIGTERM']) {
    process.on(s, async () => {
        server.close();
        await nc.drain().catch(() => {});
        process.exit(0);
    });
}
