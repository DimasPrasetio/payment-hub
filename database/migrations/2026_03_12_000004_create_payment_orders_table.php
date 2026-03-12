<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id()->comment('Internal PK — tidak diekspos ke API');
            $table->string('public_id', 30)->unique()->comment('pay_ + ULID — diekspos sebagai payment_id di API');
            $table->unsignedBigInteger('application_id');
            $table->string('tenant_id', 100)->nullable()->comment('Tenant ID dari aplikasi client');
            $table->string('external_order_id', 100)->comment('ID order dari aplikasi client');
            $table->string('idempotency_key', 100)->nullable()->unique()->comment('Mencegah duplikasi transaksi');
            $table->string('merchant_ref', 100)->unique()->comment('Format: {APP_CODE}-{YYYYMMDD}-{RANDOM}');
            $table->string('provider_code', 50)->comment('Provider yang digunakan');
            $table->string('payment_method', 50)->comment('Internal payment method code');
            $table->string('customer_name', 100);
            $table->string('customer_email', 150);
            $table->string('customer_phone', 30)->nullable();
            $table->unsignedBigInteger('amount')->comment('Nominal dalam Rupiah');
            $table->string('currency', 5)->default('IDR');
            $table->string('status', 20)->default('CREATED')->comment('CREATED, PENDING, PAID, FAILED, EXPIRED, REFUNDED');
            $table->json('metadata')->nullable()->comment('Data tambahan dari client (disimpan, tidak diproses)');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('application_id')
                ->references('id')
                ->on('applications')
                ->onDelete('restrict');

            $table->foreign('provider_code')
                ->references('code')
                ->on('payment_providers')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Composite unique: external_order_id unik per aplikasi
            $table->unique(['application_id', 'external_order_id'], 'uq_app_external_order');

            // Indexes untuk query filter & sorting
            $table->index('status');
            $table->index('payment_method');
            $table->index('provider_code');
            $table->index('created_at');
            $table->index(['application_id', 'status']);
            $table->index(['application_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
};
