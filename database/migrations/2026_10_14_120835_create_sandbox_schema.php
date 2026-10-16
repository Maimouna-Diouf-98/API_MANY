<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 */
return new class extends Migration
{
    // ─── Connexion cible ────────────────────────────────────────────────────
    public function getConnection(): string
    {
        return 'mysql_sandbox';
    }

    public function up(): void
    {
        // ────────────────────────────────────────────────────────────────────
        // 1. aggregators
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_sandbox')->hasTable('aggregators')) {
            Schema::connection('mysql_sandbox')->create('aggregators', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('user_id')->nullable();   // FK → users.id sandbox
                $table->string('legal_name');
                $table->string('trade_name')->nullable();
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->enum('status', [
                    'SANDBOX_ACTIVE',
                    'SUSPENDED',
                    'CLOSED',
                ])->default('SANDBOX_ACTIVE');
                $table->boolean('sandbox_enabled')->default(true);
                $table->boolean('production_enabled')->default(false);
                $table->string('webhook_url');
                $table->json('ip_whitelist')->nullable();
                $table->decimal('commission_rate', 5, 2)->default(0.00);
                $table->timestamp('activated_at')->nullable();
                $table->timestamps();
            });
        }

        // ────────────────────────────────────────────────────────────────────
        // 2. applications
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_sandbox')->hasTable('applications')) {
            Schema::connection('mysql_sandbox')->create('applications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('aggregator_id');
                $table->foreign('aggregator_id')
                      ->references('id')
                      ->on('aggregators')
                      ->cascadeOnDelete();
                $table->string('name');
                $table->string('client_id')->unique();   // agg_sandbox_xxx
                $table->text('client_secret');           // cast 'encrypted' dans le modèle
                $table->enum('environment', ['SANDBOX'])->default('SANDBOX');
                $table->string('webhook_url');
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        // ────────────────────────────────────────────────────────────────────
        // 3. sub_merchants
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_sandbox')->hasTable('sub_merchants')) {
            Schema::connection('mysql_sandbox')->create('sub_merchants', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('application_id');
                $table->foreign('application_id')
                      ->references('id')
                      ->on('applications')
                      ->cascadeOnDelete();
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

        // ────────────────────────────────────────────────────────────────────
        // 4. personal_access_tokens  (Sanctum — tokenable_id en string/uuid)
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_sandbox')->hasTable('personal_access_tokens')) {
            Schema::connection('mysql_sandbox')->create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('tokenable_type');
                $table->string('tokenable_id', 36);         // uuid de l'Application
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
        // 5. api_transactions  (collections sandbox)
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_sandbox')->hasTable('api_transactions')) {
            Schema::connection('mysql_sandbox')->create('api_transactions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('transaction_id')->unique();
                $table->string('sub_merchant_id', 36);
                $table->string('aggregator_id', 36);
                $table->string('application_id', 36);
                $table->enum('type', ['COLLECTION'])->default('COLLECTION');
                $table->enum('status', ['PENDING', 'SUCCESS', 'FAILED', 'REFUNDED'])
                      ->default('PENDING');
                $table->string('idempotency_key')->nullable()->unique();
                $table->string('order_reference');
                $table->string('customer_phone');
                $table->decimal('amount', 15, 2);
                $table->decimal('many_fee', 15, 2)->default(0);
                $table->decimal('net_to_aggregator', 15, 2)->default(0);
                $table->string('currency', 3)->default('XOF');
                $table->string('failure_reason')->nullable();
                $table->string('environment')->default('SANDBOX');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index('aggregator_id');
                $table->index('sub_merchant_id');
                $table->index('status');
            });
        }

        // ────────────────────────────────────────────────────────────────────
        // 6. api_disbursements  (décaissements sandbox)
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_sandbox')->hasTable('api_disbursements')) {
            Schema::connection('mysql_sandbox')->create('api_disbursements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('disbursement_id')->unique();
                $table->string('sub_merchant_id', 36);
                $table->string('aggregator_id', 36);
                $table->string('application_id', 36);
                $table->unsignedBigInteger('sender_id')->nullable();    // user_id agrégateur
                $table->unsignedBigInteger('receiver_id')->nullable();  // user_id destinataire
                $table->enum('status', ['PENDING', 'SUCCESS', 'FAILED'])->default('PENDING');
                $table->string('recipient_phone');
                $table->decimal('amount', 15, 2);
                $table->decimal('many_fee', 15, 2)->default(0);
                $table->decimal('net_amount', 15, 2)->default(0);
                $table->string('currency', 3)->default('XOF');
                $table->string('reference')->nullable();
                $table->string('idempotency_key')->nullable()->unique();
                $table->string('failure_reason')->nullable();
                $table->string('environment')->default('SANDBOX');
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();

                $table->index('aggregator_id');
                $table->index('sub_merchant_id');
            });
        }

        // ────────────────────────────────────────────────────────────────────
        // 7. api_bulk_payments  (paiements de masse sandbox)
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_sandbox')->hasTable('api_bulk_payments')) {
            Schema::connection('mysql_sandbox')->create('api_bulk_payments', function (Blueprint $table) {
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
                $table->string('environment')->default('SANDBOX');
                $table->string('webhook_url')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();

                $table->index('aggregator_id');
                $table->index('sub_merchant_id');
            });
        }

        // ────────────────────────────────────────────────────────────────────
        // 8. api_bulk_payment_recipients
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_sandbox')->hasTable('api_bulk_payment_recipients')) {
            Schema::connection('mysql_sandbox')->create('api_bulk_payment_recipients', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('bulk_id');                              // FK logique → api_bulk_payments.bulk_id
                $table->unsignedBigInteger('sender_id')->nullable();   // user_id agrégateur
                $table->unsignedBigInteger('receiver_id')->nullable(); // user_id destinataire
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
        // 9. users sandbox  (clients de test + agrégateurs sandbox)
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_sandbox')->hasTable('users')) {
            Schema::connection('mysql_sandbox')->create('users', function (Blueprint $table) {
                $table->id();
                $table->enum('role', [
                    'admin', 'master_agent', 'agent', 'merchant',
                    'user', 'mirror', 'bank', 'aggregator',
                ])->nullable();
                $table->string('reference_account_no')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('password')->nullable();
                $table->string('mpin')->nullable();
                $table->string('fcm_token')->nullable();
                $table->integer('user_status')->default(0);
                $table->integer('verification_status')->default(1);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ────────────────────────────────────────────────────────────────────
        // 10. wallets sandbox
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_sandbox')->hasTable('wallets')) {
            Schema::connection('mysql_sandbox')->create('wallets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->decimal('balance', 15, 2)->default(0.00);
                $table->decimal('negative_balance_limit', 15, 2)->default(0.00);
                $table->string('currency')->default('FCFA');
                $table->enum('status', ['active', 'blocked', 'frozen'])->default('active');
                $table->timestamp('last_transaction_at')->nullable();
                $table->unsignedBigInteger('version')->default(1);
                $table->timestamps();
                $table->softDeletes();

                $table->index('user_id');
            });
        }

        // ────────────────────────────────────────────────────────────────────
        // 11. admin_wallets sandbox  (frais Many sandbox)
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_sandbox')->hasTable('admin_wallets')) {
            Schema::connection('mysql_sandbox')->create('admin_wallets', function (Blueprint $table) {
                $table->id();
                $table->decimal('balance', 15, 2)->default(0.00);
                $table->string('currency')->default('FCFA');
                $table->enum('status', ['active', 'blocked', 'frozen'])->default('active');
                $table->timestamp('last_transaction_at')->nullable();
                $table->unsignedBigInteger('version')->default(1);
                $table->timestamps();
            });

            // Insérer le wallet Many sandbox
            DB::connection('mysql_sandbox')->table('admin_wallets')->insert([
                'balance'    => 0.00,
                'currency'   => 'FCFA',
                'status'     => 'active',
                'version'    => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ────────────────────────────────────────────────────────────────────
        // 12. jobs sandbox  (queue Laravel)
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_sandbox')->hasTable('jobs')) {
            Schema::connection('mysql_sandbox')->create('jobs', function (Blueprint $table) {
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
        // 13. job_batches sandbox
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_sandbox')->hasTable('job_batches')) {
            Schema::connection('mysql_sandbox')->create('job_batches', function (Blueprint $table) {
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
        // 14. failed_jobs sandbox
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_sandbox')->hasTable('failed_jobs')) {
            Schema::connection('mysql_sandbox')->create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        // ────────────────────────────────────────────────────────────────────
        // 15. production_requests  (demandes de passage en production)
        // ────────────────────────────────────────────────────────────────────
        if (!Schema::connection('mysql_sandbox')->hasTable('production_requests')) {
            Schema::connection('mysql_sandbox')->create('production_requests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('aggregator_id', 36);
                $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
                $table->text('message')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index('aggregator_id');
            });
        }


     
    }

    public function down(): void
    {
        $tables = [
            'production_requests',
            'failed_jobs',
            'job_batches',
            'jobs',
            'admin_wallets',
            'wallets',
            'users',
            'api_bulk_payment_recipients',
            'api_bulk_payments',
            'api_disbursements',
            'api_transactions',
            'personal_access_tokens',
            'sub_merchants',
            'applications',
            'aggregators',
        ];

        foreach ($tables as $table) {
            Schema::connection('mysql_sandbox')->dropIfExists($table);
        }
    }
};