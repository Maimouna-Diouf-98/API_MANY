<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_sandbox')->create('production_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('aggregator_id', 36);
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->text('message')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_sandbox')->dropIfExists('production_requests');
    }
};