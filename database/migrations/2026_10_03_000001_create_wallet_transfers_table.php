<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_address', 42);
            $table->string('recipient_address', 42);
            $table->string('amount_wei');          // stored as string to avoid overflow
            $table->string('amount_display');       // human-readable, e.g. "0.001"
            $table->string('tx_hash', 66)->nullable()->unique();
            $table->enum('status', ['pending', 'confirmed', 'failed'])->default('pending');
            $table->string('error_message')->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->timestamps();

            $table->index('sender_id');
            $table->index('recipient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transfers');
    }
};
