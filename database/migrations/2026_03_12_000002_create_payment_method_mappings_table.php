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
        Schema::create('payment_method_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('internal_code', 50)->comment('Kode internal: QRIS, BANK_TRANSFER_BCA, dll');
            $table->string('provider_code', 50)->comment('FK logis ke payment_providers.code');
            $table->string('provider_method_code', 50)->comment('Kode asli di provider: BRIVA, BNIVA, dll');
            $table->string('display_name', 100);
            $table->string('group', 50)->nullable()->comment('Grup: e-wallet, bank_transfer, credit_card, dll');
            $table->string('icon_url')->nullable();
            $table->unsignedInteger('fee_flat')->default(0)->comment('Biaya flat dalam Rupiah');
            $table->decimal('fee_percent', 5, 2)->default(0)->comment('Biaya persen');
            $table->unsignedBigInteger('min_amount')->nullable()->comment('Minimum transaksi');
            $table->unsignedBigInteger('max_amount')->nullable()->comment('Maksimum transaksi');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Composite unique: satu internal_code per provider
            $table->unique(['internal_code', 'provider_code'], 'uq_internal_code_provider');

            $table->index('provider_code');
            $table->index('is_active');

            $table->foreign('provider_code')
                ->references('code')
                ->on('payment_providers')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_method_mappings');
    }
};
