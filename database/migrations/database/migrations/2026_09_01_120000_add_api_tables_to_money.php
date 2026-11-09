<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exécuter UNIQUEMENT avec : php artisan migrate --database=mysql_money
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ajouter colonnes API à la table transactions existante
        Schema::connection('mysql_money')->table('transactions', function (Blueprint $table) {
            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'sub_merchant_id')) {
                $table->string('sub_merchant_id', 36)->nullable()->after('id');
            }
            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'aggregator_id')) {
                $table->string('aggregator_id', 36)->nullable()->after('sub_merchant_id');
            }
            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'application_id')) {
                $table->string('application_id', 36)->nullable()->after('aggregator_id');
            }
            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'api_type')) {
                $table->enum('api_type', ['COLLECTION', 'DISBURSEMENT'])->nullable()->after('application_id');
            }
            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique()->after('api_type');
            }
            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'order_reference')) {
                $table->string('order_reference')->nullable()->after('idempotency_key');
            }
            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'customer_phone')) {
                $table->string('customer_phone')->nullable()->after('order_reference');
            }
            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'many_fee')) {
                $table->decimal('many_fee', 15, 2)->nullable()->after('customer_phone');
            }
            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'net_to_aggregator')) {
                $table->decimal('net_to_aggregator', 15, 2)->nullable()->after('many_fee');
            }
            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'failure_reason')) {
                $table->string('failure_reason')->nullable()->after('net_to_aggregator');
            }
        });

        // Table aggregators production
        if (!Schema::connection('mysql_money')->hasTable('aggregators')) {
            Schema::connection('mysql_money')->create('aggregators', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('legal_name');
                $table->string('trade_name')->nullable();
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->enum('status', [
                    'PRODUCTION_PENDING', 'PRODUCTION_ACTIVE', 'SUSPENDED', 'CLOSED'
                ])->default('PRODUCTION_PENDING');
                $table->boolean('sandbox_enabled')->default(false);
                $table->boolean('production_enabled')->default(false);
                $table->string('webhook_url');
                $table->json('ip_whitelist')->nullable();
                $table->decimal('commission_rate', 5, 2)->default(0.00);
                $table->timestamp('activated_at')->nullable();
                $table->timestamps();
            });
        }

        // Table applications production
        if (!Schema::connection('mysql_money')->hasTable('applications')) {
            Schema::connection('mysql_money')->create('applications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('aggregator_id', 36);
                $table->string('name');
                $table->string('client_id')->unique();
                $table->text('client_secret');
                $table->enum('environment', ['PRODUCTION'])->default('PRODUCTION');
                $table->string('webhook_url');
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        // Table sub_merchants production
        if (!Schema::connection('mysql_money')->hasTable('sub_merchants')) {
            Schema::connection('mysql_money')->create('sub_merchants', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('application_id', 36);
                $table->string('legal_name');
                $table->string('business_type');
                $table->text('address');
                $table->string('webhook_url');
                $table->string('external_reference')->nullable();
                $table->enum('status', ['PENDING', 'ACTIVE', 'SUSPENDED', 'CLOSED'])
                      ->default('PENDING');
                $table->timestamps();
            });
        }

        // personal_access_tokens production (si pas déjà là)
        if (!Schema::connection('mysql_money')->hasTable('personal_access_tokens')) {
            Schema::connection('mysql_money')->create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('tokenable_type');
                $table->string('tokenable_id', 36);
                $table->index(['tokenable_type', 'tokenable_id']);
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_money')->dropIfExists('sub_merchants');
        Schema::connection('mysql_money')->dropIfExists('applications');
        Schema::connection('mysql_money')->dropIfExists('aggregators');
    }
};