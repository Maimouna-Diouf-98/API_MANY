<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sandbox
        if (Schema::connection('mysql_sandbox')->hasTable('api_disbursements')) {
            Schema::connection('mysql_sandbox')->table('api_disbursements', function (Blueprint $table) {
                if (!Schema::connection('mysql_sandbox')->hasColumn('api_disbursements', 'sender_id')) {
                    $table->unsignedBigInteger('sender_id')->nullable()->after('aggregator_id');
                }
                if (!Schema::connection('mysql_sandbox')->hasColumn('api_disbursements', 'receiver_id')) {
                    $table->unsignedBigInteger('receiver_id')->nullable()->after('sender_id');
                }
                if (!Schema::connection('mysql_sandbox')->hasColumn('api_disbursements', 'confirmed_at')) {
                    $table->timestamp('confirmed_at')->nullable()->after('completed_at');
                }
            });
        }

        // Production
        if (Schema::connection('mysql_money')->hasTable('api_disbursements')) {
            Schema::connection('mysql_money')->table('api_disbursements', function (Blueprint $table) {
                if (!Schema::connection('mysql_money')->hasColumn('api_disbursements', 'sender_id')) {
                    $table->unsignedBigInteger('sender_id')->nullable()->after('aggregator_id');
                }
                if (!Schema::connection('mysql_money')->hasColumn('api_disbursements', 'receiver_id')) {
                    $table->unsignedBigInteger('receiver_id')->nullable()->after('sender_id');
                }
                if (!Schema::connection('mysql_money')->hasColumn('api_disbursements', 'confirmed_at')) {
                    $table->timestamp('confirmed_at')->nullable()->after('completed_at');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_sandbox')->table('api_disbursements', function (Blueprint $table) {
            $table->dropColumn(['sender_id', 'receiver_id', 'confirmed_at']);
        });
        Schema::connection('mysql_money')->table('api_disbursements', function (Blueprint $table) {
            $table->dropColumn(['sender_id', 'receiver_id', 'confirmed_at']);
        });
    }
};