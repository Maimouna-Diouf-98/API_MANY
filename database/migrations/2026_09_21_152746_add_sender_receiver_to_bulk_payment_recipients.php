<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sandbox
        Schema::connection('mysql_sandbox')->table('api_bulk_payment_recipients', function (Blueprint $table) {
            if (!Schema::connection('mysql_sandbox')->hasColumn('api_bulk_payment_recipients', 'sender_id')) {
                $table->unsignedBigInteger('sender_id')->nullable()->after('bulk_id');
            }
            if (!Schema::connection('mysql_sandbox')->hasColumn('api_bulk_payment_recipients', 'receiver_id')) {
                $table->unsignedBigInteger('receiver_id')->nullable()->after('sender_id');
            }
        });

        // Production
        Schema::connection('mysql_money')->table('api_bulk_payment_recipients', function (Blueprint $table) {
            if (!Schema::connection('mysql_money')->hasColumn('api_bulk_payment_recipients', 'sender_id')) {
                $table->unsignedBigInteger('sender_id')->nullable()->after('bulk_id');
            }
            if (!Schema::connection('mysql_money')->hasColumn('api_bulk_payment_recipients', 'receiver_id')) {
                $table->unsignedBigInteger('receiver_id')->nullable()->after('sender_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_sandbox')->table('api_bulk_payment_recipients', function (Blueprint $table) {
            $table->dropColumn(['sender_id', 'receiver_id']);
        });
        Schema::connection('mysql_money')->table('api_bulk_payment_recipients', function (Blueprint $table) {
            $table->dropColumn(['sender_id', 'receiver_id']);
        });
    }
};