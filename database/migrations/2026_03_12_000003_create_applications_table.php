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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('Kode unik aplikasi: BLASKU, SAAS1');
            $table->string('name', 100);
            $table->string('api_key', 100)->unique()->comment('API key untuk autentikasi, hashed');
            $table->string('default_provider', 50)->default('tripay');
            $table->string('webhook_url', 500);
            $table->string('webhook_secret', 255)->comment('Secret untuk HMAC signature webhook');
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index('status');

            $table->foreign('default_provider')
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
        Schema::dropIfExists('applications');
    }
};
