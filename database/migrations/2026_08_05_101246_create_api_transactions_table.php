```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transaction_id')->unique();
            $table->string('sub_merchant_id', 36);
            $table->string('aggregator_id', 36);
            $table->string('application_id', 36);
            $table->enum('type', ['COLLECTION', 'DISBURSEMENT']);
            $table->enum('status', ['PENDING', 'SUCCESS', 'FAILED', 'REFUNDED'])
                  ->default('PENDING');
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('order_reference');
            $table->string('customer_phone');
            $table->decimal('amount', 15, 2);
            $table->decimal('aggregator_commission', 15, 2)->default(0);
            $table->decimal('many_fee', 15, 2)->default(0);
            $table->decimal('net_settled_amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('XOF');
            $table->text('description')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('environment')->default('SANDBOX');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('sub_merchant_id');
            $table->index('aggregator_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_transactions');
    }
};
