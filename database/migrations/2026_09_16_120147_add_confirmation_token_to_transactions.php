<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table sandbox
        Schema::connection('mysql_sandbox')->table('api_transactions', function (Blueprint $table) {
            if (!Schema::connection('mysql_sandbox')->hasColumn('api_transactions', 'confirmation_token')) {
                $table->string('confirmation_token')->nullable()->after('idempotency_key');
                $table->timestamp('confirmation_expires_at')->nullable()->after('confirmation_token');
            }
        });

        // Table production
        Schema::connection('mysql_money')->table('transactions', function (Blueprint $table) {
            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'confirmation_token')) {
                $table->string('confirmation_token')->nullable()->after('idempotency_key');
                $table->timestamp('confirmation_expires_at')->nullable()->after('confirmation_token');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_sandbox')->table('api_transactions', function (Blueprint $table) {
            $table->dropColumn(['confirmation_token', 'confirmation_expires_at']);
        });
        Schema::connection('mysql_money')->table('transactions', function (Blueprint $table) {
            $table->dropColumn(['confirmation_token', 'confirmation_expires_at']);
        });
    }
};