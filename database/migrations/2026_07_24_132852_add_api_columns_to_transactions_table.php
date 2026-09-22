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

            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'sub_merchant_id')) {
                $table->string('sub_merchant_id', 36)
                    ->nullable()
                    ->after('id');
            }

            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'aggregator_id')) {
                $table->string('aggregator_id', 36)
                    ->nullable()
                    ->after('sub_merchant_id');
            }

            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'api_type')) {
                $table->enum('api_type', ['COLLECTION', 'DISBURSEMENT'])
                    ->nullable()
                    ->after('aggregator_id');
            }

            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'idempotency_key')) {
                $table->string('idempotency_key')
                    ->nullable()
                    ->unique()
                    ->after('api_type');
            }

            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'order_reference')) {
                $table->string('order_reference')
                    ->nullable()
                    ->after('idempotency_key');
            }

            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'customer_phone')) {
                $table->string('customer_phone')
                    ->nullable()
                    ->after('order_reference');
            }

            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'aggregator_commission')) {
                $table->decimal('aggregator_commission', 15, 2)
                    ->nullable()
                    ->after('customer_phone');
            }

            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'many_fee')) {
                $table->decimal('many_fee', 15, 2)
                    ->nullable()
                    ->after('aggregator_commission');
            }

            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'net_settled_amount')) {
                $table->decimal('net_settled_amount', 15, 2)
                    ->nullable()
                    ->after('many_fee');
            }

            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'description')) {
                $table->text('description')
                    ->nullable()
                    ->after('net_settled_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_money')->table('transactions', function (Blueprint $table) {

            $columns = [
                'sub_merchant_id',
                'aggregator_id',
                'api_type',
                'idempotency_key',
                'order_reference',
                'customer_phone',
                'aggregator_commission',
                'many_fee',
                'net_settled_amount',
                'description',
            ];

            foreach ($columns as $column) {
                if (Schema::connection('mysql_money')->hasColumn('transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
