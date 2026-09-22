```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Mettre une valeur par défaut sur les lignes existantes
        DB::table('aggregators')
            ->whereNull('webhook_url')
            ->update(['webhook_url' => 'https://webhook.many.sn/default']);

        DB::table('applications')
            ->whereNull('webhook_url')
            ->update(['webhook_url' => 'https://webhook.many.sn/default']);

        DB::table('sub_merchants')
            ->whereNull('webhook_url')
            ->update(['webhook_url' => 'https://webhook.many.sn/default']);

        // Augmenter la taille de la colonne et la rendre NOT NULL
        Schema::table('aggregators', function (Blueprint $table) {
            $table->string('webhook_url', 1000)->nullable(false)->change();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('webhook_url', 1000)->nullable(false)->change();
        });

        Schema::table('sub_merchants', function (Blueprint $table) {
            $table->string('webhook_url', 1000)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('aggregators', function (Blueprint $table) {
            $table->string('webhook_url', 1000)->nullable()->change();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('webhook_url', 1000)->nullable()->change();
        });

        Schema::table('sub_merchants', function (Blueprint $table) {
            $table->string('webhook_url', 1000)->nullable()->change();
        });
    }
};
