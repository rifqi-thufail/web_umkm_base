<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DokuPaymentService;
use App\Models\Order;

class TestDokuPayment extends Command
{
    protected $signature = 'doku:test-payment {order_id}';
    protected $description = 'Test DOKU payment creation with a specific order';

    public function handle()
    {
        $orderId = $this->argument('order_id');
        
        try {
            $order = Order::find($orderId);
            
            if (!$order) {
                $this->error("Order {$orderId} not found");
                return 1;
            }

            $dokuService = app(DokuPaymentService::class);
            
            $this->info("Testing DOKU payment for order ID: {$orderId}");
            $this->info("Order total: Rp " . number_format($order->total_amount, 0, ',', '.'));
            
            // Test Virtual Account creation
            $this->line("");
            $this->info("Testing Virtual Account creation...");
            
            $vaResult = $dokuService->createVirtualAccount($order, 'mandiri');
            
            if ($vaResult) {
                $this->info("✅ Virtual Account created successfully");
                $this->line("VA Number: {$vaResult['virtual_account_number']}");
                $this->line("Expiry: {$vaResult['expired_at']}");
                $this->line("Amount: Rp " . number_format($vaResult['amount'], 0, ',', '.'));
            } else {
                $this->error("❌ Failed to create Virtual Account");
            }
            
            // Test Direct Debit
            $this->line("");
            $this->info("Testing Direct Debit URL generation...");
            
            // For testing, we'll use mock values for charge token and auth code
            $chargeToken = 'mock_charge_token_' . time();
            $authCode = 'mock_auth_' . rand(100000, 999999);
            
            $directDebitResult = $dokuService->createDirectDebitPayment($order, 'allo_bank', $chargeToken, $authCode);
            
            if ($directDebitResult) {
                $this->info("✅ Direct Debit created successfully");
                if (isset($directDebitResult['redirect_url'])) {
                    $this->line("Redirect URL: {$directDebitResult['redirect_url']}");
                }
                if (isset($directDebitResult['payment_id'])) {
                    $this->line("Payment ID: {$directDebitResult['payment_id']}");
                }
            } else {
                $this->error("❌ Failed to create Direct Debit");
            }

            // Test E-Wallet
            $this->line("");
            $this->info("Testing E-Wallet payment...");
            
            $channels = $dokuService->getAvailableChannels();
            if (isset($channels['e_wallet'])) {
                $walletChannel = array_key_first($channels['e_wallet']);
                $this->info("Testing with wallet: {$walletChannel}");
                
                // Note: E-wallet typically requires customer binding first
                $this->line("E-wallet payments require customer account binding in production");
            } else {
                $this->warn("No e-wallet channels available");
            }
            
        } catch (\Exception $e) {
            $this->error("Error testing DOKU payment: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}