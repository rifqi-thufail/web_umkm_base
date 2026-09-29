<?php

namespace App\Console\Commands;

use App\Services\DokuPaymentService;
use Illuminate\Console\Command;

class TestDokuService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'doku:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test DOKU service initialization';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing DOKU service initialization...');

        try {
            $dokuService = new DokuPaymentService();
            $channels = $dokuService->getAvailableChannels();
            
            $this->info('✅ DOKU service initialized successfully!');
            $this->info('Available channels:');
            
            foreach ($channels as $type => $typeChannels) {
                $this->info("  {$type}:");
                foreach ($typeChannels as $key => $name) {
                    $this->line("    - {$key}: {$name}");
                }
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Failed to initialize DOKU service:');
            $this->error($e->getMessage());
            return 1;
        }
    }
}