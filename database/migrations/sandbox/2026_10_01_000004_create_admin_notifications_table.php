<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration 4 — Notifications admin + suivi des décisions (mysql_sandbox)
 *
 * - admin_notifications : notifications affichées dans la cloche de l'admin
 * - production_requests : ajout de reviewed_by (admins.id) et admin_note (motif)
 *
 * La table `admins` (mysql_money) existe déjà : elle n'est ni créée ni modifiée ici.
 *
 * Exécuter avec : php artisan migrate --database=mysql_sandbox
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

        if (!$db->hasTable('admin_notifications')) {
            $db->create('admin_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('type', 50);                   // PRODUCTION_REQUEST, ...
                $table->string('title');
                $table->text('body')->nullable();
                $table->string('related_id', 36)->nullable(); // ex: id de la production_request
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index('read_at');
                $table->index(['type', 'related_id']);
            });
        }

        if ($db->hasTable('production_requests')) {
            $db->table('production_requests', function (Blueprint $table) use ($db) {
                if (!$db->hasColumn('production_requests', 'reviewed_by')) {
                    // admins.id (table dans mysql_money : référence logique, pas de clé étrangère)
                    $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
                }
                if (!$db->hasColumn('production_requests', 'admin_note')) {
                    $table->text('admin_note')->nullable()->after('reviewed_by');
                }
            });
        }
    }

    public function down(): void
    {
        $db = Schema::connection('mysql_sandbox');

        $db->dropIfExists('admin_notifications');

        if ($db->hasTable('production_requests')) {
            $db->table('production_requests', function (Blueprint $table) use ($db) {
                foreach (['reviewed_by', 'admin_note'] as $col) {
                    if ($db->hasColumn('production_requests', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
