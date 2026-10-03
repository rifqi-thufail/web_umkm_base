<?php

namespace App\Services\Mpc;

use RuntimeException;

/**
 * A failed MPC wallet operation. $userMessage is safe to show to the buyer; the exception
 * message itself may contain internal detail and is for logs only.
 *
 * $notBroadcast is true only when the transaction certainly never reached the network
 * (rejected by policy, by the signer before signing, or by the nodes), so the transfer can be
 * marked failed. Otherwise the outcome is unknown and must be reconciled against the chain.
 */
class MpcException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $userMessage = 'Transfer gagal diproses. Silakan coba lagi nanti.',
        public readonly bool $notBroadcast = true,
        public readonly ?string $txHash = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
