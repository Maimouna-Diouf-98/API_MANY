```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_bulk_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('bulk_id')->unique();
            $table->string('aggregator_id', 36);
            $table->string('application_id', 36);
            $table->string('label');

            $table->enum('status', [
                'PENDING',
                'PROCESSING',
                'COMPLETED',
                'FAILED'
            ])->default('PENDING');

            $table->integer('total_recipients')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('many_fee', 15, 2)->default(0);
            $table->string('currency', 3)->default('XOF');
            $table->string('environment')->default('SANDBOX');
            $table->string('webhook_url')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('aggregator_id');
            $table->index('status');
        });

        Schema::create('api_bulk_payment_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('bulk_id');
            $table->string('phone');
            $table->decimal('amount', 15, 2);
            $table->decimal('many_fee', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->string('reference')->nullable();
            $table->enum('status', [
                'PENDING',
                'SUCCESS',
                'FAILED'
            ])->default('PENDING');
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index('bulk_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_bulk_payment_recipients');
        Schema::dropIfExists('api_bulk_payments');
    }
};