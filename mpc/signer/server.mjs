// HTTP bridge between Laravel and the Mpcium cluster.
//
// Laravel cannot speak NATS, so this process holds one Mpcium client and exposes:
//   POST /wallets  { wallet_id }                    -> { wallet_id, address }
//   POST /send     { wallet_id, from, to, value_wei } -> { tx_hash }
//   GET  /health
// Requests must carry "Authorization: Bearer $MPC_SIGNER_TOKEN".
// No private key ever exists here: the nodes run threshold ECDSA and only return (r, s, v).
import http from 'node:http';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { KeyType, MpciumClient } from '@fystack/mpcium-ts';
import { ethers } from 'ethers';
import { connect } from 'nats';

const here = path.dirname(fileURLToPath(import.meta.url));
const PORT = Number(process.env.MPC_SIGNER_PORT || 7077);
const TOKEN = process.env.MPC_SIGNER_TOKEN || '';
const NATS_URL = process.env.MPC_NATS_URL || 'nats://127.0.0.1:4222';
const RPC_URL = process.env.BASE_RPC_URL || 'http://127.0.0.1:8545';
const KEY_PATH = path.join(here, '..', 'cluster', 'event_initiator.key');
const TIMEOUT_MS = 90_000;

const nc = await connect({ servers: NATS_URL });
const mpc = await MpciumClient.create({ nc, keyPath: KEY_PATH, clientID: 'ampuh-laravel' });
const provider = new ethers.JsonRpcProvider(RPC_URL);

// Results arrive asynchronously on JetStream; match them to waiting requests by id.
// A sign result can land before signTransaction() has returned its tx id, so unclaimed
// results are parked briefly in `early` and picked up by waitFor().
const pendingKeygen = new Map();
const pendingSign = new Map();
const early = new Map();

function waitFor(map, id) {
    return new Promise((resolve, reject) => {
        if (early.has(id)) {
            const { error, value } = early.get(id);
            early.delete(id);
            return error ? reject(new Error(error)) : resolve(value);
        }
        const timer = setTimeout(() => {
            map.delete(id);
            reject(new Error(`MPC request ${id} timed out — are all nodes running?`));
        }, TIMEOUT_MS);
        map.set(id, { resolve, reject, timer });
    });
}

function settle(map, id, error, value) {
    const p = map.get(id);
    if (!p) {
        early.set(id, { error, value });
        setTimeout(() => early.delete(id), TIMEOUT_MS);
        return;
    }
    map.delete(id);
    clearTimeout(p.timer);
    error ? p.reject(new Error(error)) : p.resolve(value);
}

mpc.onWalletCreationResult((e) => {
    settle(pendingKeygen, e.wallet_id, e.result_type === 'error' ? e.error_reason || 'keygen failed' : null, e);
});

mpc.onSignResult((e) => {
    settle(pendingSign, e.tx_id, e.result_type === 'success' ? null : e.error_reason || 'signing failed', e);
});

function addressFromPubKey(b64) {
    const bytes = Buffer.from(b64, 'base64');
    const uncompressed = bytes.length === 65 ? bytes : Buffer.concat([Buffer.from([4]), bytes]);
    return ethers.computeAddress(ethers.hexlify(uncompressed));
}

async function createWallet({ wallet_id }) {
    if (!/^[A-Za-z0-9_-]{1,64}$/.test(wallet_id || '')) throw httpError(422, 'invalid wallet_id');
    // Mpcium replays the stored result for an existing wallet id, so retries are safe.
    const result = waitFor(pendingKeygen, wallet_id);
    await mpc.createWallet(wallet_id);
    const event = await result;
    if (!event.ecdsa_pub_key) throw new Error('keygen result has no ECDSA key');
    return { wallet_id, address: addressFromPubKey(event.ecdsa_pub_key) };
}

// One in-flight transaction per sender, so concurrent sends don't reuse a nonce.
const senderQueues = new Map();
function serialize(key, fn) {
    const prev = senderQueues.get(key) || Promise.resolve();
    const next = prev.catch(() => {}).then(fn);
    senderQueues.set(key, next);
    next.finally(() => senderQueues.get(key) === next && senderQueues.delete(key));
    return next;
}

async function send({ wallet_id, from, to, value_wei }) {
    if (!wallet_id || !ethers.isAddress(from) || !ethers.isAddress(to) || !/^\d+$/.test(String(value_wei))) {
        throw httpError(422, 'wallet_id, from, to and value_wei are required');
    }

    return serialize(from.toLowerCase(), async () => {
        const [network, nonce, fees] = await Promise.all([
            provider.getNetwork(),
            provider.getTransactionCount(from, 'pending'),
            provider.getFeeData(),
        ]);

        const tx = ethers.Transaction.from({
            to,
            value: BigInt(value_wei),
            gasLimit: 21000n,
            nonce,
            chainId: network.chainId,
            ...(fees.maxFeePerGas
                ? { type: 2, maxFeePerGas: fees.maxFeePerGas, maxPriorityFeePerGas: fees.maxPriorityFeePerGas ?? 0n }
                : { type: 0, gasPrice: fees.gasPrice }),
        });

        const balance = await provider.getBalance(from);
        const cost = tx.value + tx.gasLimit * (tx.maxFeePerGas ?? tx.gasPrice);
        if (balance < cost) throw httpError(422, `insufficient funds: need ${ethers.formatEther(cost)} ETH incl. gas`);

        const txId = await mpc.signTransaction({
            walletId: wallet_id,
            keyType: KeyType.Secp256k1,
            networkInternalCode: `evm:${network.chainId}`,
            tx: Buffer.from(tx.unsignedHash.slice(2), 'hex').toString('base64'),
        });
        const sig = await waitFor(pendingSign, txId);

        tx.signature = {
            r: '0x' + Buffer.from(sig.r, 'base64').toString('hex'),
            s: '0x' + Buffer.from(sig.s, 'base64').toString('hex'),
            v: Buffer.from(sig.signature_recovery, 'base64')[0],
        };
        if (tx.from?.toLowerCase() !== from.toLowerCase()) {
            throw new Error(`signature recovers to ${tx.from}, expected ${from}`);
        }

        const sent = await provider.broadcastTransaction(tx.serialized);
        return { tx_hash: sent.hash };
    });
}

function httpError(status, message) {
    return Object.assign(new Error(message), { status });
}

const routes = { 'POST /wallets': createWallet, 'POST /send': send, 'GET /health': async () => ({ ok: true }) };

http.createServer(async (req, res) => {
    const reply = (status, body) => {
        res.writeHead(status, { 'content-type': 'application/json' });
        res.end(JSON.stringify(body));
    };
    try {
        if (TOKEN && req.headers.authorization !== `Bearer ${TOKEN}`) return reply(401, { error: 'unauthorized' });
        const handler = routes[`${req.method} ${req.url}`];
        if (!handler) return reply(404, { error: 'not found' });

        let raw = '';
        for await (const chunk of req) raw += chunk;
        reply(200, await handler(raw ? JSON.parse(raw) : {}));
    } catch (err) {
        console.error(`[signer] ${req.method} ${req.url}:`, err.message);
        reply(err.status || 500, { error: err.message });
    }
}).listen(PORT, '127.0.0.1', () => console.log(`[signer] listening on http://127.0.0.1:${PORT} (rpc ${RPC_URL})`));

for (const sig of ['SIGINT', 'SIGTERM']) {
    process.on(sig, async () => {
        await mpc.cleanup();
        await nc.drain();
        process.exit(0);
    });
}
