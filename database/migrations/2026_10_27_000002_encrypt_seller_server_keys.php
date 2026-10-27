<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seller Midtrans server keys were stored in plain text. Encrypt them at rest with APP_KEY
 * (the Seller model now casts the column as 'encrypted'); ciphertext needs a TEXT column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->text('server_key')->nullable()->change();
        });

        DB::table('sellers')->whereNotNull('server_key')->where('server_key', '!=', '')->orderBy('id')
            ->each(function ($seller) {
                try {
                    Crypt::decryptString($seller->server_key);
                } catch (\Illuminate\Contracts\Encryption\DecryptException) {
                    DB::table('sellers')->where('id', $seller->id)->update(['server_key' => Crypt::encryptString($seller->server_key)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('sellers')->whereNotNull('server_key')->where('server_key', '!=', '')->orderBy('id')
            ->each(function ($seller) {
                try {
                    DB::table('sellers')->where('id', $seller->id)->update(['server_key' => Crypt::decryptString($seller->server_key)]);
                } catch (\Illuminate\Contracts\Encryption\DecryptException) {
                    // already plain text
                }
            });

        Schema::table('sellers', function (Blueprint $table) {
            $table->string('server_key')->nullable()->change();
        });
    }
};
