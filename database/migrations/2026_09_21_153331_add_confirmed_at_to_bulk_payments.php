<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sandbox
        if (Schema::connection('mysql_sandbox')->hasTable('api_bulk_payments')) {
            Schema::connection('mysql_sandbox')->table('api_bulk_payments', function (Blueprint $table) {
                if (!Schema::connection('mysql_sandbox')->hasColumn('api_bulk_payments', 'confirmed_at')) {
                    $table->timestamp('confirmed_at')->nullable()->after('completed_at');
                }
            });
        }

        // Production
        if (Schema::connection('mysql_money')->hasTable('api_bulk_payments')) {
            Schema::connection('mysql_money')->table('api_bulk_payments', function (Blueprint $table) {
                if (!Schema::connection('mysql_money')->hasColumn('api_bulk_payments', 'confirmed_at')) {
                    $table->timestamp('confirmed_at')->nullable()->after('completed_at');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_sandbox')->table('api_bulk_payments', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });
        Schema::connection('mysql_money')->table('api_bulk_payments', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });
    }
};