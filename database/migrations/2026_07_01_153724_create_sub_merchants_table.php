<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_merchants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')
               ->constrained('applications')
               ->cascadeOnDelete();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('email')->unique();
            $table->string('phone');
            $table->enum('status', [
                'PENDING',
                'ACTIVE',
                'SUSPENDED',
                'CLOSED'
            ])->default('PENDING');
            $table->string('settlement_type');
            $table->string('settlement_operator');
            $table->string('settlement_number');
            $table->json('payment_methods');
            $table->string('webhook_url')->nullable();
            $table->string('external_reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_merchants');
    }
};