<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hapus kolom mid dari orders jika ada
        if (Schema::hasColumn('orders', 'mid')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('mid');
            });
        }

        // Hapus kolom mid dari sellers jika ada
        if (Schema::hasColumn('sellers', 'mid')) {
            try {
                Schema::table('sellers', function (Blueprint $table) {
                    $table->dropUnique(['mid']);
                });
            } catch (\Throwable $e) {
                // Abaikan jika index tidak ditemukan
            }

            Schema::table('sellers', function (Blueprint $table) {
                $table->dropColumn('mid');
            });
        }
    }

    public function down(): void
    {
        // Kembalikan kolom mid sebagai nullable string (tanpa unique) — jika Anda ingin rollback migration revert
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'mid')) {
                $table->string('mid')->nullable()->after('seller_id');
            }
        });

        Schema::table('sellers', function (Blueprint $table) {
            if (!Schema::hasColumn('sellers', 'mid')) {
                $table->string('mid')->nullable()->after('email');
            }
        });
    }
};