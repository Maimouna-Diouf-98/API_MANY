<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): string
    {
        return 'mysql_sandbox';
    }

    public function up(): void
    {
        if (!Schema::connection('mysql_sandbox')->hasTable('cache')) {
            Schema::connection('mysql_sandbox')->create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (!Schema::connection('mysql_sandbox')->hasTable('cache_locks')) {
            Schema::connection('mysql_sandbox')->create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_sandbox')->dropIfExists('cache_locks');
        Schema::connection('mysql_sandbox')->dropIfExists('cache');
    }
};