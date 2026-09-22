<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SandboxDataSeeder extends Seeder
{
    public function run(): void
    {
        // Clients de test +221770000001 à +221770000099
        for ($i = 1; $i <= 99; $i++) {
            $phone = '+221770000' . str_pad($i, 3, '0', STR_PAD_LEFT);

            $existing = DB::connection('mysql_sandbox')
                          ->table('users')
                          ->where('phone', $phone)
                          ->first();

            if (!$existing) {
                $userId = DB::connection('mysql_sandbox')
                            ->table('users')
                            ->insertGetId([
                                'role'                 => 'user',
                                'reference_account_no' => 'TST' . str_pad($i, 3, '0', STR_PAD_LEFT),
                                'first_name'           => 'Client Test',
                                'last_name'            => $i,
                                'phone'                => $phone,
                                'user_status'          => 0,
                                'verification_status'  => 1,
                                'created_at'           => now(),
                                'updated_at'           => now(),
                            ]);

                DB::connection('mysql_sandbox')
                  ->table('wallets')
                  ->insert([
                      'user_id'    => $userId,
                      'balance'    => 500000.00,
                      'currency'   => 'FCFA',
                      'status'     => 'active',
                      'version'    => 1,
                      'created_at' => now(),
                      'updated_at' => now(),
                  ]);

                $this->command->info("✓ Créé : $phone (500 000 XOF)");
            } else {
                $this->command->warn("→ Existe déjà : $phone");
            }
        }

        $this->command->info('');
        $this->command->info('✅ Seeder terminé !');
        $this->command->info('Numéros disponibles : +221770000001 à +221770000099');
    }
}