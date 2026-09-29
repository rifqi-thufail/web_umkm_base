<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DOKU Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for DOKU payment gateway integration for production use.
    | This includes all necessary credentials and settings for secure payment processing.
    |
    */

    // DOKU API Credentials - Required for authentication
    'client_id' => env('DOKU_CLIENT_ID'),
    'secret_key' => env('DOKU_SECRET_KEY'),
    
    // Merchant RSA Key Pair - Generate using OpenSSL commands in README
    'private_key' => env('DOKU_PRIVATE_KEY'),
    'public_key' => env('DOKU_PUBLIC_KEY'),
    
    // DOKU Public Key - Provided by DOKU for request verification
    'doku_public_key' => env('DOKU_PUBLIC_KEY'),
    
    // Environment Configuration
    'is_production' => env('DOKU_IS_PRODUCTION', false),
    'issuer' => env('DOKU_ISSUER', 'MARKETPLACE_UMKM'),
    
    // Partner Service Configuration for Virtual Accounts
    'partner_service_id' => env('DOKU_PARTNER_SERVICE_ID'),
    
    // Payment Channels Configuration
    'channels' => [
        // Virtual Account Channels
        'virtual_account' => [
            'cimb' => 'VIRTUAL_ACCOUNT_BANK_CIMB',
            'bri' => 'VIRTUAL_ACCOUNT_BANK_BRI',
            'mandiri' => 'VIRTUAL_ACCOUNT_BANK_MANDIRI',
            'bca' => 'VIRTUAL_ACCOUNT_BANK_BCA',
            'bni' => 'VIRTUAL_ACCOUNT_BANK_BNI',
        ],
        
        // Direct Debit Channels
        'direct_debit' => [
            'allo_bank' => 'DIRECT_DEBIT_ALLO_SNAP',
            'bri' => 'DIRECT_DEBIT_BRI_SNAP',
            'cimb' => 'DIRECT_DEBIT_CIMB_SNAP',
        ],
        
        // E-Wallet Channels
        'e_wallet' => [
            'ovo' => 'EMONEY_OVO_SNAP',
            'dana' => 'EMONEY_DANA_SNAP',
            'shopee_pay' => 'EMONEY_SHOPEE_PAY_SNAP',
        ],
    ],
    
    // Transaction Configuration
    'currency' => 'IDR',
    'virtual_account_config' => [
        'reusable_status' => false,
        'transaction_type' => 'C', // C = Closed Amount, O = Open Amount
        'expiration_hours' => 24, // VA expires in 24 hours
    ],
    
    // Webhook URLs for payment notifications
    'notification_url' => env('APP_URL') . '/api/doku/notification',
    'return_url' => env('APP_URL') . '/payment/success',
    'cancel_url' => env('APP_URL') . '/payment/cancel',
    
    // Security Configuration
    'signature_algorithm' => 'SHA256withRSA',
    'hmac_algorithm' => 'HMAC_SHA512',
    'encryption_algorithm' => 'AES-256',
];