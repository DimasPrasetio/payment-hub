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
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id()->comment('Internal PK — tidak diekspos ke API');
            $table->string('public_id', 30)->unique()->comment('wh_ + ULID — diekspos sebagai id di API');
            $table->unsignedBigInteger('payment_order_id');
            $table->unsignedBigInteger('application_id');
            $table->string('event_type', 50)->comment('payment.created, payment.paid, dll');
            $table->string('target_url', 500);
            $table->json('request_body')->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1)->comment('Nomor percobaan (max 6)');
            $table->string('status', 20)->default('pending')->comment('pending, success, failed');
            $table->timestamp('next_retry_at')->nullable()->comment('Jadwal retry berikutnya');
            $table->timestamp('created_at')->nullable();

            $table->foreign('payment_order_id')
                ->references('id')
                ->on('payment_orders')
                ->onDelete('cascade');

            $table->foreign('application_id')
                ->references('id')
                ->on('applications')
                ->onDelete('restrict');

            $table->index('status');
            $table->index('event_type');
            $table->index(['payment_order_id', 'created_at']);
            $table->index(['application_id', 'status']);
            $table->index('next_retry_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
