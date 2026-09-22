<?php

namespace App\Jobs;

use App\Domain\Transactions\Models\Transaction;
use App\Domain\Transactions\Models\RealTransaction;
use App\Domain\Auth\Models\Aggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Transaction|RealTransaction $transaction,
        public string                      $environment,
        public string                      $customerPhone,
        public Aggregator                  $aggregator,
    ) {}

    public function handle(): void
    {
        $this->environment === 'SANDBOX'
            ? $this->processSandbox()
            : $this->processProduction();
    }

    private function processSandbox(): void
    {
        try {
            DB::connection('mysql_sandbox')->transaction(function () {

                $customer = DB::connection('mysql_sandbox')
                              ->table('users')
                              ->where('phone', $this->customerPhone)
                              ->first();

                if ($customer) {
                    $customerWallet = DB::connection('mysql_sandbox')
                                        ->table('wallets')
                                        ->where('user_id', $customer->id)
                                        ->where('status', 'active')
                                        ->lockForUpdate()
                                        ->first();

                    if ($customerWallet && $customerWallet->balance >= $this->transaction->amount) {
                        DB::connection('mysql_sandbox')
                          ->table('wallets')
                          ->where('user_id', $customer->id)
                          ->update([
                              'balance'             => DB::raw('balance - ' . $this->transaction->amount),
                              'last_transaction_at' => now(),
                              'version'             => DB::raw('version + 1'),
                              'updated_at'          => now(),
                          ]);

                        $refreshed = DB::connection('mysql_sandbox')
                                       ->table('wallets')
                                       ->where('user_id', $customer->id)
                                       ->value('balance');
                        if ($refreshed < 50000) {
                            DB::connection('mysql_sandbox')
                              ->table('wallets')
                              ->where('user_id', $customer->id)
                              ->update(['balance' => 500000.00, 'updated_at' => now()]);
                        }
                    }
                }

                if ($this->aggregator->user_id) {
                    DB::connection('mysql_sandbox')
                      ->table('wallets')
                      ->where('user_id', $this->aggregator->user_id)
                      ->update([
                          'balance'             => DB::raw('balance + ' . $this->transaction->net_to_aggregator),
                          'last_transaction_at' => now(),
                          'version'             => DB::raw('version + 1'),
                          'updated_at'          => now(),
                      ]);
                }

                $adminWallet = DB::connection('mysql_sandbox')
                                 ->table('admin_wallets')
                                 ->lockForUpdate()
                                 ->first();

                if ($adminWallet) {
                    DB::connection('mysql_sandbox')
                      ->table('admin_wallets')
                      ->where('id', $adminWallet->id)
                      ->update([
                          'balance'             => DB::raw('balance + ' . $this->transaction->many_fee),
                          'last_transaction_at' => now(),
                          'version'             => DB::raw('version + 1'),
                          'updated_at'          => now(),
                      ]);
                }

                $this->transaction->update([
                    'status'       => 'SUCCESS',
                    'completed_at' => now(),
                ]);

                dispatch(new SendWebhookJob($this->transaction, 'transaction.succeeded'));
            });

        } catch (\Exception $e) {
            Log::error('SANDBOX Transaction Error: ' . $e->getMessage());
            $this->failTransaction('PROCESSING_ERROR'); 
        }
    }

    private function processProduction(): void
    {
        try {
            DB::connection('mysql_money')->transaction(function () {

                $customerWallet = DB::connection('mysql_money')
                                    ->table('wallets')
                                    ->where('user_id', $this->transaction->sender_id)
                                    ->where('status', 'active')
                                    ->lockForUpdate()
                                    ->first();

                if (!$customerWallet || $customerWallet->balance < $this->transaction->amount) {
                    $this->failTransaction('INSUFFICIENT_FUNDS');
                    return;
                }

                DB::connection('mysql_money')
                  ->table('wallets')
                  ->where('user_id', $this->transaction->sender_id)
                  ->update([
                      'balance'             => DB::raw('balance - ' . $this->transaction->amount),
                      'last_transaction_at' => now(),
                      'version'             => DB::raw('version + 1'),
                      'updated_at'          => now(),
                  ]);

                // Créditer l'agrégateur
if ($this->aggregator->user_id) {
    DB::connection('mysql_money')
      ->table('wallets')
      ->where('user_id', $this->aggregator->user_id)
      ->update([
          'balance'             => DB::raw('balance + ' . $this->transaction->net_to_aggregator), // ← corrigé
          'last_transaction_at' => now(),
          'version'             => DB::raw('version + 1'),
          'updated_at'          => now(),
      ]);
}

                $adminWallet = DB::connection('mysql_money')
                                 ->table('admin_wallets')
                                 ->lockForUpdate()
                                 ->first();

                if ($adminWallet) {
                    DB::connection('mysql_money')
                      ->table('admin_wallets')
                      ->where('id', $adminWallet->id)
                      ->update([
                          'balance'             => DB::raw('balance + ' . $this->transaction->many_fee),
                          'last_transaction_at' => now(),
                          'version'             => DB::raw('version + 1'),
                          'updated_at'          => now(),
                      ]);
                }

                $this->transaction->update([
                    'status'     => 'success',
                    'updated_at' => now(),
                ]);
                Log::info('Dispatching SendWebhookJob', ['transaction_id' => $this->transaction->transaction_id]);

                dispatch(new SendWebhookJob($this->transaction, 'transaction.succeeded'));
            });

        } catch (\Exception $e) {
            Log::error('PRODUCTION Transaction Error: ' . $e->getMessage());
            $this->failTransaction('PROCESSING_ERROR');
        }
    }

    private function failTransaction(string $reason): void
    {
        if ($this->transaction instanceof RealTransaction) {
            $this->transaction->update(['status' => 'failed', 'failure_reason' => $reason]);
        } else {
            $this->transaction->update([
                'status'         => 'FAILED',
                'failure_reason' => $reason,
                'completed_at'   => now(),
            ]);
        }
        dispatch(new SendWebhookJob($this->transaction, 'transaction.failed'));
    }
}