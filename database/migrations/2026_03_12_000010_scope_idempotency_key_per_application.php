<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->unique(['application_id', 'idempotency_key'], 'uq_app_idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropUnique('uq_app_idempotency_key');
            $table->unique('idempotency_key');
        });
    }
};
