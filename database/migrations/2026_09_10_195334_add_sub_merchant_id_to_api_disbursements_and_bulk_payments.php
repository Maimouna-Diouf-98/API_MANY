<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
   

    Schema::connection('mysql_sandbox')
        ->table('api_bulk_payments', function (Blueprint $table) {
            $table->string('sub_merchant_id', 36)
                ->nullable()
                ->after('bulk_id');

            $table->index('sub_merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    Schema::connection('mysql_sandbox')
        ->table('api_bulk_payments', function (Blueprint $table) {
            $table->dropIndex(['sub_merchant_id']);
            $table->dropColumn('sub_merchant_id');
        });
    }
};
