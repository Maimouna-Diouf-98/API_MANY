<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute 'refunded' à l'enum status de transactions (mysql_money),
 * nécessaire au remboursement des collections API (POST /v1/transactions/{id}/refund).
 *
 * Idempotente : conserve les valeurs existantes et ne fait rien si 'refunded' est déjà présent.
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'mysql_money';
    }

    public function up(): void
    {
        $values = $this->currentValues();

        if ($values === null || in_array('refunded', $values, true)) {
            return;
        }

        $this->setValues([...$values, 'refunded']);
    }

    public function down(): void
    {
        $values = $this->currentValues();

        if ($values === null || !in_array('refunded', $values, true)) {
            return;
        }

        // Impossible de retirer la valeur tant que des lignes l'utilisent
        if (DB::connection('mysql_money')->table('transactions')->where('status', 'refunded')->exists()) {
            throw new RuntimeException("Des transactions ont le statut 'refunded' : rollback impossible.");
        }

        $this->setValues(array_values(array_diff($values, ['refunded'])));
    }

    /** @return string[]|null */
    private function currentValues(): ?array
    {
        $column = DB::connection('mysql_money')
                    ->select("SHOW COLUMNS FROM transactions WHERE Field = 'status'")[0] ?? null;

        if (!$column || !preg_match("/^enum\((.*)\)$/i", $column->Type, $m)) {
            return null;
        }

        return str_getcsv($m[1], ',', "'");
    }

    private function setValues(array $values): void
    {
        $enum = implode(',', array_map(fn ($v) => "'" . str_replace("'", "''", $v) . "'", $values));

        DB::connection('mysql_money')->statement(
            "ALTER TABLE transactions MODIFY COLUMN status ENUM({$enum}) NULL DEFAULT 'pending'"
        );
    }
};
