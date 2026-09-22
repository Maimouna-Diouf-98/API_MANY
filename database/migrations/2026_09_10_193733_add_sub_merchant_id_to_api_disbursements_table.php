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
            ->table('api_disbursements', function (Blueprint $table) {
                $table->uuid('sub_merchant_id')
                    ->nullable()
                    ->after('disbursement_id');
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql_sandbox')
            ->table('api_disbursements', function (Blueprint $table) {
                $table->dropColumn('sub_merchant_id');
            });
            
    }
};
