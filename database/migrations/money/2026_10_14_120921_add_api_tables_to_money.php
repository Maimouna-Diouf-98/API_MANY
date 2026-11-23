<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration 2 — Tables API dans mysql_money (production)
 *
 * NE TOUCHE PAS aux tables existantes du projet mobile money
 * (users, wallets, transactions, admins...).
 *
 * Crée uniquement les nouvelles tables API et ajoute
 * les colonnes API à la table transactions existante.
 *
 * Exécuter avec :
 *   php artisan migrate --database=mysql_money
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'mysql_money';
    }

    public function up(): void
    {
        // ────────────────────────────────────────────────────────────────────
        // 1. aggregators production
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_money')->hasTable('aggregators')) {
            Schema::connection('mysql_money')->create('aggregators', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('user_id')->nullable();   // FK → users.id money
                $table->string('legal_name');
                $table->string('trade_name')->nullable();
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->enum('status', [
                    'PRODUCTION_PENDING',
                    'PRODUCTION_ACTIVE',
                    'SUSPENDED',
                    'CLOSED',
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

        // ────────────────────────────────────────────────────────────────────
        // 2. applications production
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_money')->hasTable('applications')) {
            Schema::connection('mysql_money')->create('applications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('aggregator_id', 36);
                $table->string('name');
                $table->string('client_id')->unique();   // agg_live_xxx
                $table->text('client_secret');           // cast 'encrypted' dans le modèle
                $table->enum('environment', ['PRODUCTION'])->default('PRODUCTION');
                $table->string('webhook_url');
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index('aggregator_id');
            });
        }

        // ────────────────────────────────────────────────────────────────────
        // 3. sub_merchants production
        // ────────────────────────────────────────────────────────────────────
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

                $table->index('application_id');
            });
        }

        // ────────────────────────────────────────────────────────────────────
        // 4. personal_access_tokens production (si pas déjà là)
        // ────────────────────────────────────────────────────────────────────
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

        // ────────────────────────────────────────────────────────────────────
        // 5. Colonnes API sur la table transactions existante
        //    Toutes nullable → zéro impact sur les transactions mobiles
        // ────────────────────────────────────────────────────────────────────
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
            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'confirmation_token')) {
                $table->string('confirmation_token')->nullable()->after('idempotency_key');
            }
            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'confirmation_expires_at')) {
                $table->timestamp('confirmation_expires_at')->nullable()->after('confirmation_token');
            }
            if (!Schema::connection('mysql_money')->hasColumn('transactions', 'order_reference')) {
                $table->string('order_reference')->nullable()->after('confirmation_expires_at');
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

        // ────────────────────────────────────────────────────────────────────
        // 6. api_disbursements production
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_money')->hasTable('api_disbursements')) {
            Schema::connection('mysql_money')->create('api_disbursements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('disbursement_id')->unique();
                $table->string('sub_merchant_id', 36);
                $table->string('aggregator_id', 36);
                $table->string('application_id', 36);
                $table->unsignedBigInteger('sender_id')->nullable();   // user_id agrégateur
                $table->unsignedBigInteger('receiver_id')->nullable(); // user_id destinataire
                $table->enum('status', ['PENDING', 'SUCCESS', 'FAILED'])->default('PENDING');
                $table->string('recipient_phone');
                $table->decimal('amount', 15, 2);
                $table->decimal('many_fee', 15, 2)->default(0);
                $table->decimal('net_amount', 15, 2)->default(0);
                $table->string('currency', 3)->default('XOF');
                $table->string('reference')->nullable();
                $table->string('idempotency_key')->nullable()->unique();
                $table->string('failure_reason')->nullable();
                $table->string('environment')->default('PRODUCTION');
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();

                $table->index('aggregator_id');
                $table->index('sub_merchant_id');
            });
        }

        // ────────────────────────────────────────────────────────────────────
        // 7. api_bulk_payments production
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_money')->hasTable('api_bulk_payments')) {
            Schema::connection('mysql_money')->create('api_bulk_payments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('bulk_id')->unique();
                $table->string('sub_merchant_id', 36);
                $table->string('aggregator_id', 36);
                $table->string('application_id', 36);
                $table->string('label');
                $table->enum('status', ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED'])
                      ->default('PENDING');
                $table->integer('total_recipients')->default(0);
                $table->integer('success_count')->default(0);
                $table->integer('failure_count')->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->decimal('many_fee', 15, 2)->default(0);
                $table->string('currency', 3)->default('XOF');
                $table->string('environment')->default('PRODUCTION');
                $table->string('webhook_url')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();

                $table->index('aggregator_id');
                $table->index('sub_merchant_id');
            });
        }

        // ────────────────────────────────────────────────────────────────────
        // 8. api_bulk_payment_recipients production
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_money')->hasTable('api_bulk_payment_recipients')) {
            Schema::connection('mysql_money')->create('api_bulk_payment_recipients', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('bulk_id');
                $table->unsignedBigInteger('sender_id')->nullable();
                $table->unsignedBigInteger('receiver_id')->nullable();
                $table->string('phone');
                $table->decimal('amount', 15, 2);
                $table->decimal('many_fee', 15, 2)->default(0);
                $table->decimal('net_amount', 15, 2)->default(0);
                $table->string('reference')->nullable();
                $table->enum('status', ['PENDING', 'SUCCESS', 'FAILED'])->default('PENDING');
                $table->string('failure_reason')->nullable();
                $table->timestamps();

                $table->index('bulk_id');
            });
        }

        // ────────────────────────────────────────────────────────────────────
        // 9. jobs production  (queue Laravel)
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_money')->hasTable('jobs')) {
            Schema::connection('mysql_money')->create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        // ────────────────────────────────────────────────────────────────────
        // 10. job_batches production
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_money')->hasTable('job_batches')) {
            Schema::connection('mysql_money')->create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        // ────────────────────────────────────────────────────────────────────
        // 11. failed_jobs production
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_money')->hasTable('failed_jobs')) {
            Schema::connection('mysql_money')->create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        // Supprimer colonnes API de transactions
        Schema::connection('mysql_money')->table('transactions', function (Blueprint $table) {
            $columns = [
                'sub_merchant_id', 'aggregator_id', 'application_id',
                'api_type', 'idempotency_key', 'confirmation_token',
                'confirmation_expires_at', 'order_reference', 'customer_phone',
                'many_fee', 'net_to_aggregator', 'failure_reason',
            ];
            $toDrop = array_values(array_filter(
                $columns,
                fn($c) => Schema::connection('mysql_money')->hasColumn('transactions', $c)
            ));
            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });

        // Supprimer les nouvelles tables (NE PAS toucher aux tables existantes)
        $tables = [
            'failed_jobs',
            'job_batches',
            'jobs',
            'api_bulk_payment_recipients',
            'api_bulk_payments',
            'api_disbursements',
            'personal_access_tokens',
            'sub_merchants',
            'applications',
            'aggregators',
        ];

        foreach ($tables as $table) {
            Schema::connection('mysql_money')->dropIfExists($table);
        }
    }
};