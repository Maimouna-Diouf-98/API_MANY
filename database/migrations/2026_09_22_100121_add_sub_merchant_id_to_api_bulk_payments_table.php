<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_money')->table('api_bulk_payments', function (Blueprint $table) {
            $table->string('sub_merchant_id')->after('bulk_id');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_money')->table('api_bulk_payments', function (Blueprint $table) {
            $table->dropColumn('sub_merchant_id');
        });
    }
};