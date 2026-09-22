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
            $table->renameColumn('net_settled_amount', 'net_to_aggregator');
        });
    }

    public function down(): void
    {
        Schema::table('api_transactions', function (Blueprint $table) {
            $table->renameColumn('net_to_aggregator', 'net_settled_amount');
        });
    }
};
