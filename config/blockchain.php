<?php

return [
    /*
    | Order anchoring on Base (OP Stack L2).
    | Local development uses an anvil fork of Base Sepolia: see blockchain/README.md.
    */
    'base' => [
        'enabled' => env('BASE_ENABLED', false),
        'network_name' => env('BASE_NETWORK_NAME', 'Base Sepolia (local fork)'),
        'rpc_url' => env('BASE_RPC_URL', 'http://127.0.0.1:8545'),
        'chain_id' => (int) env('BASE_CHAIN_ID', 84532),
        'contract_address' => env('BASE_REGISTRY_ADDRESS'),
        'private_key' => env('BASE_RECORDER_PRIVATE_KEY'),
        // Leave empty for a local fork (no public explorer). Base Sepolia: https://sepolia.basescan.org
        'explorer_url' => env('BASE_EXPLORER_URL'),
    ],

    /*
    | Coinbase Developer Platform (CDP) Server Wallet: one MPC-secured EVM account
    | per registered buyer. Keys come from https://portal.cdp.coinbase.com
    | (API key id + secret, and the project's Wallet Secret).
    */
    'cdp' => [
        'enabled' => env('CDP_WALLET_ENABLED', false),
        'api_url' => env('CDP_API_URL', 'https://api.cdp.coinbase.com/platform'),
        'api_key_id' => env('CDP_API_KEY_ID'),
        // Ed25519 (base64, 64 bytes) or EC PEM. "\n" escapes are allowed in .env.
        'api_key_secret' => env('CDP_API_KEY_SECRET'),
        // Base64 PKCS8 DER EC key.
        'wallet_secret' => env('CDP_WALLET_SECRET'),
        // Prefix for account names; names must be unique across the CDP project.
        'account_prefix' => env('CDP_ACCOUNT_PREFIX', 'ampuh-user'),
    ],
];
