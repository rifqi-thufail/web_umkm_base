<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sellers')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE sellers MODIFY email VARCHAR(255) NULL');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sellers')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE sellers MODIFY email VARCHAR(255) NOT NULL');
            }
        }
    }
};
