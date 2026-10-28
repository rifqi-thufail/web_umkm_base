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
        // Safety limits for the recorder wallet: refuse to sign above this fee per gas (Base normally
        // charges ~0.01 gwei), and stop after this many anchors per UTC day (0 = no limit).
        'max_fee_gwei' => env('BASE_MAX_FEE_GWEI', '0.5'),
        'max_anchors_per_day' => (int) env('BASE_MAX_ANCHORS_PER_DAY', 300),
    ],

];
