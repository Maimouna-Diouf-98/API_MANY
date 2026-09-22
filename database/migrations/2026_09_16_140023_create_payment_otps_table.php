<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_money')->create('payment_otps', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id');
            $table->string('customer_phone');
            $table->string('otp_code', 6);
            $table->boolean('verified')->default(false);
            $table->integer('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('transaction_id');
            $table->index('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_money')->dropIfExists('payment_otps');
    }
};