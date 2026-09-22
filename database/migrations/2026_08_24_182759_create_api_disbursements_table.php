```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_disbursements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('disbursement_id')->unique();
            $table->string('aggregator_id', 36);
            $table->string('application_id', 36);
            $table->enum('status', ['PENDING', 'SUCCESS', 'FAILED'])->default('PENDING');
            $table->string('recipient_phone');
            $table->decimal('amount', 15, 2);
            $table->decimal('many_fee', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('XOF');
            $table->string('reference')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('failure_reason')->nullable();
            $table->string('environment')->default('SANDBOX');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('aggregator_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_disbursements');
    }
};
