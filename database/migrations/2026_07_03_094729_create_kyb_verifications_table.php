<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyb_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('aggregator_id')
                  ->unique()
                  ->constrained('aggregators')
                  ->cascadeOnDelete();
            $table->enum('status', [
                'PENDING',
                'UNDER_REVIEW',
                'APPROVED',
                'REJECTED',
                'EXPIRED'
            ])->default('PENDING');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyb_verifications');
    }
};