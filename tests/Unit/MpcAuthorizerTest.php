<?php

namespace Tests\Unit;

use App\Services\Mpc\MpcAuthorizer;
use PHPUnit\Framework\TestCase;

/**
 * Vectors cross-checked against mpcium's Go types and mpc/signer/protocol.test.mjs. If these drift,
 * the nodes reject every request with "authorizer verification failed".
 */
class MpcAuthorizerTest extends TestCase
{
    private const TX = [
        'chain_id' => '84532',
        'nonce' => '7',
        'max_fee_per_gas' => '1500000000',
        'max_priority_fee_per_gas' => '1000000',
        'gas_limit' => '21000',
        'to' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
        'value' => '250000000000000000',
    ];

    public function test_unsigned_hash_matches_ethers(): void
    {
        $this->assertSame('1ced82e3bf137d7e6e78002e2fe1db2b2d1d85b6126f52d69e3467fe624f0950', MpcAuthorizer::unsignedHash(self::TX));
        $this->assertSame('54fb447fdf82c502189767286066d8ce4ea752865b4d8c86e09f7996dbaa9f8b', MpcAuthorizer::unsignedHash([
            ...self::TX, 'nonce' => '0', 'max_fee_per_gas' => '1000000', 'max_priority_fee_per_gas' => '0', 'value' => '1',
        ]));
    }

    public function test_raw_messages_match_go(): void
    {
        $raw = MpcAuthorizer::signTxRaw('ampuh-user-1', 'evm:84532', '0b8f1a52-3c1e-4c55-9d67-2f0e7c1d9a10', hex2bin(MpcAuthorizer::unsignedHash(self::TX)));
        $this->assertSame('{"key_type":"secp256k1","wallet_id":"ampuh-user-1","network_internal_code":"evm:84532","tx_id":"0b8f1a52-3c1e-4c55-9d67-2f0e7c1d9a10","tx":"HO2C478TfX5ueAAuL+HbKy0dhbYSb1LWnjRn/mJPCVA="}', $raw);

        $sig = base64_decode('1tp1DMGoQi9TmIO3jFHiIb4Phx8AgDy17qWAMcrvcvEjiyiwwasCEgj56i4pbp+2Ue0cZRgXJccNKuq3VF6BCg==');
        $this->assertSame(
            '{"initiator_id":"ampuh-user-1","initiator_raw":"YW1wdWgtdXNlci0x","initiator_sig":"1tp1DMGoQi9TmIO3jFHiIb4Phx8AgDy17qWAMcrvcvEjiyiwwasCEgj56i4pbp+2Ue0cZRgXJccNKuq3VF6BCg=="}',
            MpcAuthorizer::authorizerRaw('ampuh-user-1', 'ampuh-user-1', $sig),
        );
    }

    public function test_public_key_matches_node_crypto(): void
    {
        $this->assertSame('d04ab232742bb4ab3a1368bd4615e4e6d0224ab71a016baf8520a332c9778737', (new MpcAuthorizer(str_repeat('11', 32), 'x'))->publicKeyHex());
    }

    public function test_rejects_bad_input(): void
    {
        $this->expectException(\RuntimeException::class);
        MpcAuthorizer::unsignedHash([...self::TX, 'value' => '1e18']);
    }

    public function test_rejects_bad_key(): void
    {
        $this->expectException(\RuntimeException::class);
        new MpcAuthorizer('not-hex', 'x');
    }
}
