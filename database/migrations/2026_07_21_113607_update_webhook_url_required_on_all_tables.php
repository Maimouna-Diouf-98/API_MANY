```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // aggregators
        Schema::table('aggregators', function (Blueprint $table) {
            $table->string('webhook_url')->nullable(false)->change();
        });

        // applications
        Schema::table('applications', function (Blueprint $table) {
            $table->string('webhook_url')->nullable(false)->change();
        });

        // sub_merchants
        Schema::table('sub_merchants', function (Blueprint $table) {
            $table->string('webhook_url')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('aggregators', function (Blueprint $table) {
            $table->string('webhook_url')->nullable()->change();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('webhook_url')->nullable()->change();
        });

        Schema::table('sub_merchants', function (Blueprint $table) {
            $table->string('webhook_url')->nullable()->change();
        });
    }
};
