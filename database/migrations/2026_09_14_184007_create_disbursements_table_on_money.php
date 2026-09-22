<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_money')->create('api_disbursements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('disbursement_id')->unique();
            $table->string('sub_merchant_id');
            $table->string('aggregator_id');
            $table->string('application_id');
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->unsignedBigInteger('receiver_id')->nullable();
            $table->string('status');
            $table->string('recipient_phone');
            $table->integer('amount');
            $table->integer('many_fee');
            $table->integer('net_amount');
            $table->string('currency');
            $table->string('reference')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('environment');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_money')->dropIfExists('api_disbursements');
    }
};