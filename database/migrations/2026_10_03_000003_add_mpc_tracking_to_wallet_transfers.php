<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transfers', function (Blueprint $table) {
            // MPC tx_id: idempotency key for the signer and the nodes.
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->unsignedBigInteger('nonce')->nullable()->after('amount_display');
            $table->timestamp('submitted_at')->nullable()->after('block_number');
            $table->timestamp('confirmed_at')->nullable()->after('submitted_at');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transfers', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'nonce', 'submitted_at', 'confirmed_at']);
        });
    }
};
