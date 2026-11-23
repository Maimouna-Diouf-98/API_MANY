<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligne api_transactions (sandbox) sur le modèle Transaction et sur
 * transactions (production) : colonnes confirmation_token / confirmation_expires_at.
 *
 * Idempotente.
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'mysql_sandbox';
    }

    public function up(): void
    {
        $db = Schema::connection('mysql_sandbox');

        if (!$db->hasTable('api_transactions')) {
            return;
        }

        $db->table('api_transactions', function (Blueprint $table) use ($db) {
            if (!$db->hasColumn('api_transactions', 'confirmation_token')) {
                $table->string('confirmation_token')->nullable()->after('idempotency_key');
            }
            if (!$db->hasColumn('api_transactions', 'confirmation_expires_at')) {
                $table->timestamp('confirmation_expires_at')->nullable()->after('confirmation_token');
            }
        });
    }

    public function down(): void
    {
        $db = Schema::connection('mysql_sandbox');

        if (!$db->hasTable('api_transactions')) {
            return;
        }

        $db->table('api_transactions', function (Blueprint $table) use ($db) {
            foreach (['confirmation_expires_at', 'confirmation_token'] as $col) {
                if ($db->hasColumn('api_transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
