<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One checkout = one payment covering one order per seller; the gateway sees this reference.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_reference', 64)->nullable()->after('order_number')->index();
            $table->timestamp('paid_at')->nullable()->after('payment_status');
        });

        // Order items point at products; a hard delete would cascade into paid (and anchored) orders.
        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_reference']);
            $table->dropColumn(['payment_reference', 'paid_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
