<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyb_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kyb_verification_id')
                  ->constrained('kyb_verifications')
                  ->cascadeOnDelete();
            $table->enum('type', [
                'REGISTRATION_CERTIFICATE',
                'TAX_ID',
                'ID_CARD_DIRECTOR',
                'BANK_STATEMENT',
                'LICENSE'
            ]);
            $table->string('file_path');
            $table->string('file_name');
            $table->enum('status', [
                'PENDING',
                'VALIDATED',
                'REJECTED'
            ])->default('PENDING');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyb_documents');
    }
};