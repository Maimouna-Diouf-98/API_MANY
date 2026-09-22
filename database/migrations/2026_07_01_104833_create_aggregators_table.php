<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aggregators', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->enum('status', [
                'SANDBOX_ACTIVE',
                'PRODUCTION_PENDING',
                'PRODUCTION_ACTIVE',
                'SUSPENDED',
                'CLOSED'
            ])->default('SANDBOX_ACTIVE');
            $table->boolean('sandbox_enabled')->default(true);
            $table->boolean('production_enabled')->default(false);
            $table->string('webhook_url')->nullable();
            $table->json('ip_whitelist')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(0.00);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aggregators');
    }
};