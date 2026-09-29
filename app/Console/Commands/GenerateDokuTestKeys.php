<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateDokuTestKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'doku:generate-test-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate test RSA keys for DOKU development';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating RSA key pair for DOKU testing...');

        // Generate private key
        $config = array(
            "digest_alg" => "sha256",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );

        // Create the private and public key
        $res = openssl_pkey_new($config);

        if (!$res) {
            $this->error('Failed to generate key pair: ' . openssl_error_string());
            return 1;
        }

        // Extract the private key
        openssl_pkey_export($res, $privateKey);

        // Extract the public key
        $publicKey = openssl_pkey_get_details($res);
        $publicKeyPem = $publicKey["key"];

        // Display the keys
        $this->info('=== PRIVATE KEY (for DOKU_PRIVATE_KEY) ===');
        $this->line($privateKey);
        
        $this->info('=== PUBLIC KEY (for DOKU_PUBLIC_KEY) ===');
        $this->line($publicKeyPem);

        $this->info('=== .env FORMAT ===');
        $this->line('DOKU_PRIVATE_KEY="' . str_replace("\n", "\\n", trim($privateKey)) . '"');
        $this->line('DOKU_PUBLIC_KEY="' . str_replace("\n", "\\n", trim($publicKeyPem)) . '"');

        $this->warn('Note: These are test keys only! Use real DOKU-provided keys for production.');

        return 0;
    }
}