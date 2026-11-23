<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattrapage : sur une installation neuve, 2026_10_01_000004 s'exécute avant
 * 2026_10_14_120835_create_sandbox_schema, donc production_requests n'existe
 * pas encore et les colonnes reviewed_by / admin_note ne sont jamais ajoutées.
 *
 * Idempotente : ne fait rien sur une base où les colonnes existent déjà.
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

        if (!$db->hasTable('production_requests')) {
            return;
        }

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

    public function down(): void
    {
        // Rien : ces colonnes appartiennent à 2026_10_01_000004, dont le down()
        // les supprime. Les retirer ici effacerait des données en cas de rollback.
    }
};
