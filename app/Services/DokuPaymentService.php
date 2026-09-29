<?php

namespace App\Services;

use Doku\Snap\Snap;
use Doku\Snap\Models\VA\Request\CreateVaRequestDto;
use Doku\Snap\Models\VA\Request\UpdateVaRequestDto;
use Doku\Snap\Models\VA\Request\DeleteVaRequestDto;
use Doku\Snap\Models\VA\Request\CheckStatusVaRequestDto;
use Doku\Snap\Models\TotalAmount\TotalAmount;
use Doku\Snap\Models\VA\AdditionalInfo\CreateVaRequestAdditionalInfo;
use Doku\Snap\Models\VA\AdditionalInfo\UpdateVaRequestAdditionalInfo;
use Doku\Snap\Models\VA\AdditionalInfo\DeleteVaRequestAdditionalInfo;
use Doku\Snap\Models\VA\VirtualAccountConfig\CreateVaVirtualAccountConfig;
use Doku\Snap\Models\VA\VirtualAccountConfig\UpdateVaVirtualAccountConfig;
use Doku\Snap\Models\Payment\PaymentRequestDto;
use Doku\Snap\Models\Payment\PaymentAdditionalInfoRequestDto;
use Doku\Snap\Models\AccountBinding\AccountBindingRequestDto;
use Doku\Snap\Models\AccountBinding\AccountBindingAdditionalInfoRequestDto;
use App\Models\Order;
use App\Models\Seller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class DokuPaymentService
{
    protected $snap;
    protected $config;

    public function __construct()
    {
        $this->config = config('doku');
        
        // Check if we're in a testing/development environment without real keys
        if ($this->isTestingEnvironment()) {
            Log::info('DOKU Service initialized in testing mode - some features disabled');
            $this->snap = null;
            return;
        }
        
        // Validate required configurations
        $this->validateConfiguration();
        
        try {
            // Process keys - handle multiline format
            $privateKey = $this->processPrivateKey($this->config['private_key']);
            $publicKey = $this->processPublicKey($this->config['public_key']);
            $dokuPublicKey = $this->processPublicKey($this->config['doku_public_key']);
            
            // Initialize DOKU Snap with configuration
            $this->snap = new Snap(
                $privateKey,
                $publicKey,
                $dokuPublicKey,
                $this->config['client_id'],
                $this->config['issuer'],
                $this->config['is_production'],
                $this->config['secret_key'],
                null // authCode - will be set when needed
            );
        } catch (\Exception $e) {
            Log::error('Failed to initialize DOKU Snap', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // In development, continue without DOKU but log the error
            if (app()->environment('local')) {
                Log::warning('DOKU initialization failed in local environment - continuing without DOKU');
                $this->snap = null;
            } else {
                throw new \Exception('DOKU configuration error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Create Virtual Account for Order Payment
     * 
     * @param Order $order
     * @param string $channel - Bank channel (cimb, bri, mandiri, bca, bni)
     * @return array
     */
    public function createVirtualAccount(Order $order, string $channel = 'cimb'): array
    {
        // Return mock response in testing mode
        if ($this->snap === null) {
            return $this->mockVirtualAccountResponse($order, $channel);
        }

        try {
            Log::info('Creating Virtual Account for order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'channel' => $channel
            ]);

            $seller = $order->seller;
            $user = $order->user;
            
            // Generate unique customer number and VA number
            $customerNo = $this->generateCustomerNumber($order);
            $virtualAccountNo = $this->generateVirtualAccountNumber($order, $channel);
            
            $totalAmount = new TotalAmount(
                number_format($order->total_price, 2, '.', ''),
                $this->config['currency']
            );

            $additionalInfo = new CreateVaRequestAdditionalInfo(
                $this->config['channels']['virtual_account'][$channel],
                new CreateVaVirtualAccountConfig(
                    $this->config['virtual_account_config']['reusable_status']
                )
            );

            $createVaRequest = new CreateVaRequestDto(
                $this->config['partner_service_id'],
                $customerNo,
                $virtualAccountNo,
                $user->nama . '_' . $order->order_number,
                $user->email,
                $user->no_hp ?? '62' . substr($user->email, 0, 10),
                $order->order_number,
                $totalAmount,
                $additionalInfo,
                $this->config['virtual_account_config']['transaction_type'],
                $this->getExpirationDate()
            );

            $result = $this->snap->createVa($createVaRequest);

            // Save VA details to order
            $order->update([
                'payment_gateway' => 'doku',
                'payment_method' => 'virtual_account',
                'payment_channel' => $channel,
                'virtual_account_number' => $virtualAccountNo,
                'payment_reference' => $result['virtualAccountData']['virtualAccountNo'] ?? $virtualAccountNo,
                'payment_expired_at' => $this->getExpirationDate()
            ]);

            Log::info('Virtual Account created successfully', [
                'order_id' => $order->id,
                'va_number' => $virtualAccountNo,
                'result' => $result
            ]);

            return [
                'success' => true,
                'data' => $result,
                'virtual_account_number' => $virtualAccountNo,
                'bank_name' => strtoupper($channel),
                'amount' => $order->total_price,
                'expired_at' => $this->getExpirationDate()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create Virtual Account', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create Direct Debit Payment for registered customer
     * 
     * @param Order $order
     * @param string $channel
     * @param string $chargeToken - Token from account binding
     * @param string $authCode - Auth code from customer
     * @return array
     */
    public function createDirectDebitPayment(Order $order, string $channel, string $chargeToken, string $authCode): array
    {
        // Return mock response in testing mode
        if ($this->snap === null) {
            Log::info('Mock Direct Debit payment created for testing', [
                'order_id' => $order->id,
                'channel' => $channel
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'responseCode' => '2002400',
                    'responseMessage' => 'Successful (Mock)',
                    'referenceNo' => 'MOCK_' . $order->order_number
                ]
            ];
        }
        try {
            Log::info('Creating Direct Debit payment for order', [
                'order_id' => $order->id,
                'channel' => $channel
            ]);

            $user = $order->user;
            $amount = new TotalAmount(
                number_format($order->total_price, 2, '.', ''),
                $this->config['currency']
            );

            $additionalInfo = new PaymentAdditionalInfoRequestDto(
                $this->config['channels']['direct_debit'][$channel],
                'Payment for order ' . $order->order_number,
                route('orders.show', $order->id),
                route('orders.checkout'),
                [], // lineItems - can be populated with order items
                'SALE' // paymentType
            );

            $paymentRequest = new PaymentRequestDto(
                $order->order_number,
                $amount,
                null, // payOptionDetails - will be set based on channel
                $additionalInfo,
                '', // feeType
                $chargeToken
            );

            $result = $this->snap->doPayment($paymentRequest, $authCode, request()->ip());

            $order->update([
                'payment_gateway' => 'doku',
                'payment_method' => 'direct_debit',
                'payment_channel' => $channel,
                'payment_reference' => $result['referenceNo'] ?? $order->order_number
            ]);

            return [
                'success' => true,
                'data' => $result
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create Direct Debit payment', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check Virtual Account Payment Status
     * 
     * @param Order $order
     * @return array
     */
    public function checkVirtualAccountStatus(Order $order): array
    {
        // Return mock response in testing mode
        if ($this->snap === null) {
            Log::info('Mock VA status check for testing', [
                'order_id' => $order->id,
                'payment_status' => $order->payment_status
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'virtualAccountData' => [
                        'paidStatus' => $order->payment_status === 'paid' ? 'Y' : 'N'
                    ]
                ],
                'paid' => $order->payment_status === 'paid'
            ];
        }
        try {
            $checkStatusRequest = new CheckStatusVaRequestDto(
                $this->config['partner_service_id'],
                $this->generateCustomerNumber($order),
                $order->virtual_account_number,
                null, // inquiryRequestId
                null, // paymentRequestId
                null  // additionalInfo
            );

            $result = $this->snap->checkStatusVa($checkStatusRequest);

            Log::info('VA status check result', [
                'order_id' => $order->id,
                'result' => $result
            ]);

            return [
                'success' => true,
                'data' => $result,
                'paid' => isset($result['virtualAccountData']) && 
                         $result['virtualAccountData']['paidStatus'] === 'Y'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to check VA status', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process Account Binding for Direct Debit
     * 
     * @param array $customerData
     * @param string $channel
     * @return array
     */
    public function bindCustomerAccount(array $customerData, string $channel): array
    {
        try {
            $additionalInfo = new AccountBindingAdditionalInfoRequestDto(
                $this->config['channels']['direct_debit'][$channel],
                $customerData['customer_id'],
                $customerData['name'],
                $customerData['email'],
                $customerData['id_card'] ?? null,
                $customerData['country'] ?? 'ID',
                $customerData['address'] ?? null,
                $customerData['date_of_birth'] ?? null,
                route('home'),
                route('home'),
                $customerData['device_model'] ?? 'web',
                $customerData['os_type'] ?? 'web',
                $customerData['channel_id'] ?? 'web'
            );

            $bindingRequest = new AccountBindingRequestDto(
                $customerData['phone_number'],
                $additionalInfo
            );

            $result = $this->snap->doAccountBinding(
                $bindingRequest, 
                request()->ip(), 
                $customerData['device_id'] ?? 'web_device'
            );

            return [
                'success' => true,
                'data' => $result
            ];

        } catch (\Exception $e) {
            Log::error('Account binding failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate DOKU notification/webhook
     * 
     * @param array $payload
     * @return bool
     */
    public function validateNotification(array $payload): bool
    {
        // Always return true in testing mode
        if ($this->snap === null) {
            Log::info('Mock notification validation for testing (always true)');
            return true;
        }
        try {
            // Implement DOKU signature validation
            $authorization = request()->header('Authorization');
            return $this->snap->validateTokenB2B($authorization);
        } catch (\Exception $e) {
            Log::error('Notification validation failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Generate unique customer number for order
     */
    private function generateCustomerNumber(Order $order): string
    {
        return substr(str_pad($order->user_id, 8, '0', STR_PAD_LEFT) . 
                     substr($order->id, -8), 0, 20);
    }

    /**
     * Generate unique virtual account number
     */
    private function generateVirtualAccountNumber(Order $order, string $channel): string
    {
        $prefix = $this->config['partner_service_id'];
        $orderSuffix = substr(str_pad($order->id, 8, '0', STR_PAD_LEFT), -8);
        return substr($prefix . $orderSuffix, 0, 20);
    }

    /**
     * Get expiration date for virtual account
     */
    private function getExpirationDate(): string
    {
        return now()->addHours($this->config['virtual_account_config']['expiration_hours'])
                   ->format('Y-m-d\TH:i:s+07:00');
    }

    /**
     * Get available payment channels
     */
    public function getAvailableChannels(): array
    {
        return [
            'virtual_account' => [
                'cimb' => 'CIMB Niaga',
                'bri' => 'Bank BRI',
                'mandiri' => 'Bank Mandiri',
                'bca' => 'Bank BCA',
                'bni' => 'Bank BNI'
            ],
            'direct_debit' => [
                'allo_bank' => 'Allo Bank',
                'bri' => 'BRI Direct Debit',
                'cimb' => 'CIMB Direct Debit'
            ],
            'e_wallet' => [
                'ovo' => 'OVO',
                'dana' => 'DANA',
                'shopee_pay' => 'ShopeePay'
            ]
        ];
    }

    /**
     * Format amount for DOKU API
     */
    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Validate DOKU configuration
     */
    private function validateConfiguration(): void
    {
        $required = ['client_id', 'secret_key', 'private_key', 'public_key', 'doku_public_key'];
        
        foreach ($required as $key) {
            if (empty($this->config[$key])) {
                throw new \Exception("DOKU configuration missing: {$key}");
            }
        }
        
        if (empty($this->config['partner_service_id'])) {
            Log::warning('DOKU partner_service_id not configured - some features may not work');
        }
    }

    /**
     * Process private key - handle various formats
     */
    private function processPrivateKey(string $key): string
    {
        // Remove quotes if present
        $key = trim($key, '"\'');
        
        // Handle escaped newlines
        $key = str_replace('\\n', "\n", $key);
        
        // Validate key format
        if (!str_contains($key, '-----BEGIN') || !str_contains($key, '-----END')) {
            throw new \Exception('Invalid private key format. Must include BEGIN and END markers.');
        }
        
        // Test if key can be loaded
        $resource = openssl_pkey_get_private($key);
        if (!$resource) {
            throw new \Exception('Invalid private key: ' . openssl_error_string());
        }
        
        return $key;
    }

    /**
     * Process public key - handle various formats
     */
    private function processPublicKey(string $key): string
    {
        // Remove quotes if present
        $key = trim($key, '"\'');
        
        // Handle escaped newlines
        $key = str_replace('\\n', "\n", $key);
        
        // Validate key format
        if (!str_contains($key, '-----BEGIN') || !str_contains($key, '-----END')) {
            throw new \Exception('Invalid public key format. Must include BEGIN and END markers.');
        }
        
        // Test if key can be loaded
        $resource = openssl_pkey_get_public($key);
        if (!$resource) {
            throw new \Exception('Invalid public key: ' . openssl_error_string());
        }
        
        return $key;
    }

    /**
     * Check if we're in a testing environment without real DOKU keys
     */
    private function isTestingEnvironment(): bool
    {
        // Check if keys are placeholder values
        $hasRealKeys = !str_contains($this->config['client_id'] ?? '', 'your_') &&
                      !str_contains($this->config['private_key'] ?? '', 'YOUR_') &&
                      !str_contains($this->config['secret_key'] ?? '', 'your_');
        
        return !$hasRealKeys || app()->environment('testing');
    }

    /**
     * Generate mock Virtual Account response for testing
     */
    private function mockVirtualAccountResponse(Order $order, string $channel): array
    {
        $mockVANumber = '8129' . str_pad($order->id, 12, '0', STR_PAD_LEFT);
        
        Log::info('Generated mock Virtual Account for testing', [
            'order_id' => $order->id,
            'va_number' => $mockVANumber,
            'channel' => $channel
        ]);

        // Update order with mock data
        $order->update([
            'payment_gateway' => 'doku',
            'payment_method' => 'virtual_account',
            'payment_channel' => $channel,
            'virtual_account_number' => $mockVANumber,
            'payment_reference' => $mockVANumber,
            'payment_expired_at' => now()->addHours(24)->toISOString()
        ]);

        return [
            'success' => true,
            'data' => [
                'responseCode' => '2002400',
                'responseMessage' => 'Successful (Mock)',
                'virtualAccountData' => [
                    'virtualAccountNo' => $mockVANumber
                ]
            ],
            'virtual_account_number' => $mockVANumber,
            'bank_name' => strtoupper($channel),
            'amount' => $order->total_price,
            'expired_at' => now()->addHours(24)->toISOString()
        ];
    }
}