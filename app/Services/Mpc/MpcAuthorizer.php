<?php

namespace App\Services\Mpc;

use kornrunner\Ethereum\EIP1559Transaction;
use RuntimeException;

/**
 * Laravel's co-signature on Mpcium requests.
 *
 * Every keygen and signing request carries two Ed25519 signatures: the signer bridge's initiator
 * key and this authorizer key. The nodes reject requests without both, so the signer cannot act on
 * its own. The bytes signed here mirror ComposeAuthorizerRaw() in mpcium's pkg/types/initiator_msg.go
 * and mpc/signer/protocol.mjs; they are always rebuilt from values Laravel has checked itself,
 * never taken from the signer's response.
 */
class MpcAuthorizer
{
    public const GAS_LIMIT = 21000;

    public function __construct(private string $seedHex, private string $authorizerId)
    {
        if (! preg_match('/^[0-9a-fA-F]{64}$/', $seedHex)) {
            throw new RuntimeException('MPC_AUTHORIZER_KEY must be a 64-character hex Ed25519 seed (run npm run mpc:setup)');
        }
    }

    public function id(): string
    {
        return $this->authorizerId;
    }

    public function publicKeyHex(): string
    {
        return bin2hex(sodium_crypto_sign_publickey($this->keypair()));
    }

    /** Authorizer signature (base64) for keygen of $walletId. */
    public function authorizeKeygen(string $walletId, string $initiatorSignatureB64): string
    {
        return $this->sign($walletId, $walletId, $initiatorSignatureB64);
    }

    /**
     * Authorizer signature (base64) for signing an EIP-1559 transfer.
     *
     * @param  array{chain_id:string,nonce:string,max_fee_per_gas:string,max_priority_fee_per_gas:string,gas_limit:string,to:string,value:string}  $tx
     */
    public function authorizeTransfer(string $walletId, string $txId, array $tx, string $initiatorSignatureB64): string
    {
        $raw = self::signTxRaw($walletId, 'evm:' . $tx['chain_id'], $txId, hex2bin(self::unsignedHash($tx)));

        return $this->sign($txId, $raw, $initiatorSignatureB64);
    }

    /** SignTxMessage.Raw(): Go's json.Marshal of the struct, []byte fields as standard base64. */
    public static function signTxRaw(string $walletId, string $networkCode, string $txId, string $txHash): string
    {
        return self::goJson([
            'key_type' => 'secp256k1',
            'wallet_id' => $walletId,
            'network_internal_code' => $networkCode,
            'tx_id' => $txId,
            'tx' => base64_encode($txHash),
        ]);
    }

    public static function authorizerRaw(string $initiatorId, string $raw, string $initiatorSig): string
    {
        return self::goJson([
            'initiator_id' => $initiatorId,
            'initiator_raw' => base64_encode($raw),
            'initiator_sig' => base64_encode($initiatorSig),
        ]);
    }

    /**
     * Keccak hash (hex, no 0x) of an unsigned EIP-1559 transfer with empty calldata and access list.
     *
     * @param  array{chain_id:string,nonce:string,max_fee_per_gas:string,max_priority_fee_per_gas:string,gas_limit:string,to:string,value:string}  $tx
     */
    public static function unsignedHash(array $tx): string
    {
        foreach (['chain_id', 'nonce', 'max_fee_per_gas', 'max_priority_fee_per_gas', 'gas_limit', 'value'] as $field) {
            if (! isset($tx[$field]) || ! preg_match('/^(0|[1-9]\d{0,77})$/', (string) $tx[$field])) {
                throw new RuntimeException("tx.{$field} must be a non-negative integer string");
            }
        }
        if (! preg_match('/^0x[0-9a-fA-F]{40}$/', (string) ($tx['to'] ?? ''))) {
            throw new RuntimeException('tx.to must be an address');
        }

        $hex = fn (string $dec) => gmp_strval(gmp_init($dec, 10), 16);
        $transaction = new EIP1559Transaction(
            $hex($tx['nonce']),
            $hex($tx['max_priority_fee_per_gas']),
            $hex($tx['max_fee_per_gas']),
            $hex($tx['gas_limit']),
            strtolower($tx['to']),
            $hex($tx['value']),
            '',
        );

        $chainId = filter_var($tx['chain_id'], FILTER_VALIDATE_INT);
        if ($chainId === false) {
            throw new RuntimeException('tx.chain_id out of range');
        }

        return $transaction->hash($chainId);
    }

    private function sign(string $initiatorId, string $raw, string $initiatorSignatureB64): string
    {
        $initiatorSig = base64_decode($initiatorSignatureB64, true);
        if ($initiatorSig === false || strlen($initiatorSig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw new RuntimeException('signer returned a malformed initiator signature');
        }

        $secret = sodium_crypto_sign_secretkey($this->keypair());
        try {
            return base64_encode(sodium_crypto_sign_detached(self::authorizerRaw($initiatorId, $raw, $initiatorSig), $secret));
        } finally {
            sodium_memzero($secret);
        }
    }

    private function keypair(): string
    {
        return sodium_crypto_sign_seed_keypair(hex2bin($this->seedHex));
    }

    /** Matches Go's encoding/json for the plain ASCII values used here (no escaped slashes). */
    private static function goJson(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
