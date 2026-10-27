<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The self-hosted MPC wallet was removed (backup: git tag mpc-wallet-backup-20261027).
 * Drops its transfer table and the wallet columns on users.
 */
return new class extends Migration
{
    private const COLUMNS = ['wallet_address', 'wallet_connected_at', 'wallet_provider', 'mpc_wallet_id'];

    public function up(): void
    {
        Schema::dropIfExists('wallet_transfers');

        // The unique index kept its pre-rename name (cdp_account_name -> mpc_wallet_id).
        foreach (['users_cdp_account_name_unique', 'users_mpc_wallet_id_unique'] as $index) {
            if (Schema::hasIndex('users', $index)) {
                Schema::table('users', fn (Blueprint $table) => $table->dropUnique($index));
            }
        }

        $columns = array_values(array_filter(self::COLUMNS, fn ($c) => Schema::hasColumn('users', $c)));
        if ($columns) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('wallet_address')->nullable()->after('email');
            $table->timestamp('wallet_connected_at')->nullable();
            $table->string('wallet_provider')->nullable();
            $table->string('mpc_wallet_id')->nullable()->unique();
        });

        Schema::create('wallet_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_address', 42);
            $table->string('recipient_address', 42);
            $table->string('amount_wei');
            $table->string('amount_display');
            $table->unsignedBigInteger('nonce')->nullable();
            $table->string('tx_hash', 66)->nullable()->unique();
            $table->enum('status', ['pending', 'confirmed', 'failed'])->default('pending');
            $table->string('error_message')->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index('sender_id');
            $table->index('recipient_id');
            $table->index(['status', 'created_at']);
        });
    }
};
