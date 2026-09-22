<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // wallets sandbox (pour les agrégateurs et clients de test)
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
        });

        // users sandbox (pour les clients de test)
        Schema::connection('mysql_sandbox')->create('users', function (Blueprint $table) {
            $table->id();
            $table->enum('role', [
                'admin', 'master_agent', 'agent', 'merchant',
                'user', 'mirror', 'bank', 'aggregator'
            ])->nullable();
            $table->string('reference_account_no')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone');
            $table->string('password')->nullable();
            $table->integer('user_status')->default(0);
            $table->integer('verification_status')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        // admin_wallets sandbox (pour les frais Many)
        Schema::connection('mysql_sandbox')->create('admin_wallets', function (Blueprint $table) {
            $table->id();
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->string('currency')->default('FCFA');
            $table->enum('status', ['active', 'blocked', 'frozen'])->default('active');
            $table->timestamp('last_transaction_at')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
        });

        // failed_jobs sandbox
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

    public function down(): void
    {
        $tables = [
            'failed_jobs', 'admin_wallets', 'wallets', 'users',
        ];

        foreach ($tables as $table) {
            Schema::connection('mysql_sandbox')->dropIfExists($table);
        }
    }
};