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
        Schema::create('provider_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_order_id');
            $table->string('provider', 50);
            $table->string('merchant_ref', 100)->comment('Merchant ref yang dikirim ke provider');
            $table->string('provider_reference', 100)->nullable()->comment('Reference/ID dari provider');
            $table->string('payment_method', 50)->nullable()->comment('Kode payment method di provider');
            $table->text('payment_url')->nullable()->comment('URL checkout/payment dari provider');
            $table->string('pay_code', 100)->nullable()->comment('Virtual account number / pay code');
            $table->text('qr_string')->nullable()->comment('QR string untuk QRIS');
            $table->text('qr_url')->nullable()->comment('URL gambar QR dari provider');
            $table->json('raw_request')->nullable()->comment('Raw request yang dikirim ke provider');
            $table->json('raw_response')->nullable()->comment('Raw response dari provider');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('payment_order_id')
                ->references('id')
                ->on('payment_orders')
                ->onDelete('cascade');

            $table->index('merchant_ref');
            $table->index('provider_reference');
            $table->index('provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_transactions');
    }
};
