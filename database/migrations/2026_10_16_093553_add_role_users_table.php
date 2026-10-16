<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration 3 — Corrections mysql_money pour les agrégateurs
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
        // 1. Ajouter 'aggregator' à l'enum role de users
        // ────────────────────────────────────────────────────────────────────
        // Récupérer les valeurs actuelles de l'enum
        $result = DB::connection('mysql_money')
                    ->select("SHOW COLUMNS FROM users WHERE Field = 'role'");

        if (!empty($result)) {
            $currentType = $result[0]->Type;

            // Si 'aggregator' n'est pas encore dans l'enum
            if (!str_contains($currentType, 'aggregator')) {
                // Extraire les valeurs existantes
                preg_match("/enum\((.*)\)/", $currentType, $matches);
                $existingValues = $matches[1] ?? "'admin','master_agent','agent','merchant','user','mirror','bank'";

                // Ajouter 'aggregator'
                DB::connection('mysql_money')->statement(
                    "ALTER TABLE users MODIFY COLUMN role ENUM({$existingValues},'aggregator') NULL"
                );
            }
        }

      

        // ────────────────────────────────────────────────────────────────────
        // 4. Corriger tokenable_id dans personal_access_tokens
        //    Le passer en string(36) pour stocker les UUID des Applications
        // ────────────────────────────────────────────────────────────────────
        if (Schema::connection('mysql_money')->hasTable('personal_access_tokens')) {
            $col = DB::connection('mysql_money')
                     ->select("SHOW COLUMNS FROM personal_access_tokens WHERE Field = 'tokenable_id'");

            if (!empty($col) && !str_contains(strtolower($col[0]->Type), 'varchar')) {
                // Supprimer l'index composite avant de modifier la colonne
                try {
                    DB::connection('mysql_money')->statement(
                        "ALTER TABLE personal_access_tokens DROP INDEX personal_access_tokens_tokenable_type_tokenable_id_index"
                    );
                } catch (\Exception $e) {
                    // L'index n'existe peut-être pas sous ce nom, on continue
                }

                // Modifier la colonne
                DB::connection('mysql_money')->statement(
                    "ALTER TABLE personal_access_tokens MODIFY COLUMN tokenable_id VARCHAR(36) NOT NULL"
                );

                // Recréer l'index
                try {
                    DB::connection('mysql_money')->statement(
                        "CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index
                         ON personal_access_tokens (tokenable_type, tokenable_id)"
                    );
                } catch (\Exception $e) {
                    // L'index existe déjà, on ignore
                }
            }
        }
    }

    public function down(): void
    {
        // On ne rollback pas le changement d'enum (risque de perte de données)
        // On peut juste noter ce qui a été fait
    }
};