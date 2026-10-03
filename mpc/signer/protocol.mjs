// Mpcium wire format: the exact bytes that the initiator and authorizers sign.
// These mirror Raw() and ComposeAuthorizerRaw() in mpcium's pkg/types/initiator_msg.go,
// and app/Services/Mpc/MpcAuthorizer.php computes the same bytes on the Laravel side.
import crypto from 'node:crypto';
import { ethers } from 'ethers';

export const KEY_TYPE = 'secp256k1';
export const GAS_LIMIT = 21000n;
export const WALLET_ID_RE = /^[A-Za-z0-9_-]{1,64}$/;
export const TX_ID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;

const PKCS8_ED25519_PREFIX = Buffer.from('302e020100300506032b657004220420', 'hex');

/** Ed25519 private key from a 32-byte hex seed (the format mpcium-cli writes). */
export function ed25519KeyFromHex(hex) {
    const seed = Buffer.from(String(hex).trim(), 'hex');
    if (seed.length !== 32) throw new Error('ed25519 key must be a 32-byte hex seed');
    return crypto.createPrivateKey({ key: Buffer.concat([PKCS8_ED25519_PREFIX, seed]), format: 'der', type: 'pkcs8' });
}

export function ed25519Sign(key, bytes) {
    return crypto.sign(null, bytes, key);
}

export function keygenRaw(walletId) {
    return Buffer.from(walletId, 'utf8');
}

/** SignTxMessage.Raw(): Go json.Marshal of the struct, fields in declaration order, []byte as base64. */
export function signTxRaw({ walletId, networkInternalCode, txId, txHash }) {
    return Buffer.from(
        JSON.stringify({
            key_type: KEY_TYPE,
            wallet_id: walletId,
            network_internal_code: networkInternalCode,
            tx_id: txId,
            tx: Buffer.from(txHash).toString('base64'),
        }),
        'utf8',
    );
}

export function authorizerRaw(initiatorId, raw, initiatorSig) {
    return Buffer.from(
        JSON.stringify({
            initiator_id: initiatorId,
            initiator_raw: Buffer.from(raw).toString('base64'),
            initiator_sig: Buffer.from(initiatorSig).toString('base64'),
        }),
        'utf8',
    );
}

export function networkCode(chainId) {
    return `evm:${chainId}`;
}

const UINT_RE = /^(0|[1-9]\d{0,77})$/;

/**
 * Plain native-ETH transfer, EIP-1559 only. Every field arrives as a decimal string so the same
 * object can travel Laravel -> signer -> Laravel without precision loss.
 */
export function buildTransfer(fields) {
    const { chain_id, nonce, max_fee_per_gas, max_priority_fee_per_gas, gas_limit, to, value } = fields || {};
    for (const [k, v] of Object.entries({ chain_id, nonce, max_fee_per_gas, max_priority_fee_per_gas, gas_limit, value })) {
        if (!UINT_RE.test(String(v))) throw Object.assign(new Error(`tx.${k} must be a non-negative integer string`), { status: 422 });
    }
    if (!ethers.isAddress(to)) throw Object.assign(new Error('tx.to must be an address'), { status: 422 });
    if (BigInt(gas_limit) !== GAS_LIMIT) throw Object.assign(new Error('only plain ETH transfers are supported'), { status: 422 });
    if (BigInt(max_priority_fee_per_gas) > BigInt(max_fee_per_gas)) {
        throw Object.assign(new Error('priority fee exceeds max fee'), { status: 422 });
    }

    return ethers.Transaction.from({
        type: 2,
        chainId: BigInt(chain_id),
        nonce: Number(nonce),
        maxFeePerGas: BigInt(max_fee_per_gas),
        maxPriorityFeePerGas: BigInt(max_priority_fee_per_gas),
        gasLimit: BigInt(gas_limit),
        to: ethers.getAddress(to),
        value: BigInt(value),
        data: '0x',
    });
}

export function transferFields(tx) {
    return {
        chain_id: tx.chainId.toString(),
        nonce: String(tx.nonce),
        max_fee_per_gas: tx.maxFeePerGas.toString(),
        max_priority_fee_per_gas: tx.maxPriorityFeePerGas.toString(),
        gas_limit: tx.gasLimit.toString(),
        to: tx.to,
        value: tx.value.toString(),
    };
}

/** Ethereum address of a secp256k1 public key (33-byte compressed or 64/65-byte uncompressed). */
export function addressFromPubKey(bytes) {
    const b = Buffer.from(bytes);
    let key;
    if (b.length === 33 || (b.length === 65 && b[0] === 4)) key = b;
    else if (b.length === 64) key = Buffer.concat([Buffer.from([4]), b]);
    else throw new Error(`unexpected public key length ${b.length}`);
    return ethers.computeAddress(ethers.hexlify(key));
}
