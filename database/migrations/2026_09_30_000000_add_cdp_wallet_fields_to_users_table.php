<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('wallet_provider')->nullable()->after('wallet_address');
            $table->string('cdp_account_name')->nullable()->unique()->after('wallet_provider');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['cdp_account_name']);
            $table->dropColumn(['wallet_provider', 'cdp_account_name']);
        });
    }
};
