```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('api_transactions', 'aggregator_commission')) {
                $table->dropColumn('aggregator_commission');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_transactions', function (Blueprint $table) {
            $table->decimal('aggregator_commission', 15, 2)
                ->default(0)
                ->nullable();
        });
    }
};
