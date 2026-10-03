// Vectors cross-checked against mpcium's Go types (SignTxMessage.Raw / ComposeAuthorizerRaw)
// and mirrored in tests/Unit/MpcAuthorizerTest.php.
import assert from 'node:assert/strict';
import test from 'node:test';
import * as p from './protocol.mjs';

const TX = {
    chain_id: '84532',
    nonce: '7',
    max_fee_per_gas: '1500000000',
    max_priority_fee_per_gas: '1000000',
    gas_limit: '21000',
    to: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
    value: '250000000000000000',
};
const TX_ID = '0b8f1a52-3c1e-4c55-9d67-2f0e7c1d9a10';
const HASH = '0x1ced82e3bf137d7e6e78002e2fe1db2b2d1d85b6126f52d69e3467fe624f0950';

test('unsigned EIP-1559 hash', () => {
    assert.equal(p.buildTransfer(TX).unsignedHash, HASH);
    assert.deepEqual(p.transferFields(p.buildTransfer(TX)), TX);
});

test('initiator raw and authorizer raw match Go', () => {
    const raw = p.signTxRaw({ walletId: 'ampuh-user-1', networkInternalCode: 'evm:84532', txId: TX_ID, txHash: Buffer.from(HASH.slice(2), 'hex') });
    assert.equal(
        raw.toString(),
        '{"key_type":"secp256k1","wallet_id":"ampuh-user-1","network_internal_code":"evm:84532","tx_id":"0b8f1a52-3c1e-4c55-9d67-2f0e7c1d9a10","tx":"HO2C478TfX5ueAAuL+HbKy0dhbYSb1LWnjRn/mJPCVA="}',
    );
    const sig = p.ed25519Sign(p.ed25519KeyFromHex('11'.repeat(32)), raw);
    assert.equal(sig.toString('base64'), '1tp1DMGoQi9TmIO3jFHiIb4Phx8AgDy17qWAMcrvcvEjiyiwwasCEgj56i4pbp+2Ue0cZRgXJccNKuq3VF6BCg==');
    assert.match(p.authorizerRaw(TX_ID, raw, sig).toString(), /^\{"initiator_id":"0b8f1a52-[^"]+","initiator_raw":"eyJr[^"]+","initiator_sig":"1tp1[^"]+"\}$/);
    assert.equal(
        p.authorizerRaw('ampuh-user-1', p.keygenRaw('ampuh-user-1'), sig).toString(),
        '{"initiator_id":"ampuh-user-1","initiator_raw":"YW1wdWgtdXNlci0x","initiator_sig":"1tp1DMGoQi9TmIO3jFHiIb4Phx8AgDy17qWAMcrvcvEjiyiwwasCEgj56i4pbp+2Ue0cZRgXJccNKuq3VF6BCg=="}',
    );
});

test('rejects anything but a plain transfer', () => {
    assert.throws(() => p.buildTransfer({ ...TX, gas_limit: '100000' }), /plain ETH/);
    assert.throws(() => p.buildTransfer({ ...TX, value: '-1' }), /non-negative/);
    assert.throws(() => p.buildTransfer({ ...TX, value: '1e18' }), /non-negative/);
    assert.throws(() => p.buildTransfer({ ...TX, max_priority_fee_per_gas: '2000000000' }), /priority/);
    assert.throws(() => p.buildTransfer({ ...TX, to: '0x123' }), /address/);
});

test('address from public key', () => {
    // secp256k1 generator point G: well-known address of private key 1.
    const g = '0279be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    assert.equal(p.addressFromPubKey(Buffer.from(g, 'hex')), '0x7E5F4552091A69125d5DfCb7b8C2659029395Bdf');
    assert.throws(() => p.addressFromPubKey(Buffer.alloc(10)), /length/);
});
