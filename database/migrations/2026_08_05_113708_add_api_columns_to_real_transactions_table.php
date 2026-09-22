```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_money')->table('transactions', function (Blueprint $table) {

            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'application_id')) {
                $table->string('application_id', 36)->nullable()->after('aggregator_id');
            }

            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'failure_reason')) {
                $table->string('failure_reason')->nullable()->after('net_settled_amount');
            }

        });
    }

    public function down(): void
    {
        Schema::connection('mysql_money')->table('transactions', function (Blueprint $table) {

            if (Schema::connection('mysql_money')->hasColumn('transactions', 'application_id')) {
                $table->dropColumn('application_id');
            }

            if (Schema::connection('mysql_money')->hasColumn('transactions', 'failure_reason')) {
                $table->dropColumn('failure_reason');
            }

        });
    }
};
