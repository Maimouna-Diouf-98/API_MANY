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
       Schema::table('sub_merchants', function (Blueprint $table) {
        $table->dropColumn([
        'trade_name',
        'registration_number',
        'phone',
        'settlement_type',
        'settlement_operator',
        'settlement_number',
        'payment_methods',
    ]);
});
    }

    /**
     * Reverse the migrations.
     */
  public function down(): void
{
    Schema::table('sub_merchants', function (Blueprint $table) {
        $table->string('trade_name')->nullable();
        $table->string('registration_number')->nullable();
        $table->string('phone')->nullable();
        $table->string('settlement_type')->nullable();
        $table->string('settlement_operator')->nullable();
        $table->string('settlement_number')->nullable();
        $table->json('payment_methods')->nullable();
    });
}
};
