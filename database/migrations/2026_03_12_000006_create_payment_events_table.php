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
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id()->comment('Internal PK — tidak diekspos ke API');
            $table->string('public_id', 30)->unique()->comment('evt_ + ULID — diekspos sebagai id di API');
            $table->unsignedBigInteger('payment_order_id');
            $table->string('event_type', 50)->comment('payment.created, payment.paid, callback.received, dll');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('payment_order_id')
                ->references('id')
                ->on('payment_orders')
                ->onDelete('cascade');

            $table->index('event_type');
            $table->index(['payment_order_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
