<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Domain\Transactions\Models\Transaction;
use App\Domain\Disbursements\Models\Disbursement;
use App\Domain\BulkPayments\Models\BulkPayment;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $aggregator = auth('aggregator')->user();
        $type       = $request->get('type', 'all');
        $status     = $request->get('status');

        // Collections
        $collections = Transaction::where('aggregator_id', $aggregator->id)
                                   ->when($status, fn($q) => $q->where('status', $status))
                                   ->get()
                                   ->map(fn($t) => [
                                       'id'           => $t->transaction_id,
                                       'type'         => 'COLLECTION',
                                       'label'        => null,
                                       'status'       => $t->status,
                                       'amount'       => $t->amount,
                                       'many_fee'     => $t->many_fee,
                                       'net'          => $t->net_to_aggregator,
                                       'phone'        => $t->customer_phone,
                                       'reference'    => $t->order_reference,
                                       'recipients'   => null,
                                       'environment'  => $t->environment,
                                       'created_at'   => $t->created_at,
                                   ]);

        // Disbursements unitaires
        $disbursements = Disbursement::where('aggregator_id', $aggregator->id)
                                      ->when($status, fn($q) => $q->where('status', $status))
                                      ->get()
                                      ->map(fn($d) => [
                                          'id'          => $d->disbursement_id,
                                          'type'        => 'DISBURSEMENT',
                                          'label'       => null,
                                          'status'      => $d->status,
                                          'amount'      => $d->amount,
                                          'many_fee'    => $d->many_fee,
                                          'net'         => $d->net_amount,
                                          'phone'       => $d->recipient_phone,
                                          'reference'   => $d->reference,
                                          'recipients'  => null,
                                          'environment' => $d->environment,
                                          'created_at'  => $d->created_at,
                                      ]);

        // Bulk payments
        $bulkPayments = BulkPayment::where('aggregator_id', $aggregator->id)
                                    ->when($status, fn($q) => $q->where('status', $status))
                                    ->get()
                                    ->map(fn($b) => [
                                        'id'          => $b->bulk_id,
                                        'type'        => 'BULK_PAYMENT',
                                        'label'       => $b->label,
                                        'status'      => $b->status,
                                        'amount'      => $b->total_amount,
                                        'many_fee'    => $b->many_fee,
                                        'net'         => $b->total_amount - $b->many_fee,
                                        'phone'       => null,
                                        'reference'   => null,
                                        'recipients'  => $b->total_recipients,
                                        'environment' => $b->environment,
                                        'created_at'  => $b->created_at,
                                    ]);

        // Fusionner selon le filtre type
        if ($type === 'collection') {
            $all = $collections;
        } elseif ($type === 'disbursement') {
            $all = $disbursements;
        } elseif ($type === 'bulk') {
            $all = $bulkPayments;
        } else {
            $all = $collections
                    ->concat($disbursements)
                    ->concat($bulkPayments);
        }

        $all = $all->sortByDesc('created_at')->values();

        // Pagination manuelle
        $page    = $request->get('page', 1);
        $perPage = 20;
        $total   = $all->count();
        $items   = $all->slice(($page - 1) * $perPage, $perPage)->values();

        // Totaux
        $totalCollections  = Transaction::where('aggregator_id', $aggregator->id)
                                        ->where('status', 'SUCCESS')->count();
        $totalAmount       = Transaction::where('aggregator_id', $aggregator->id)
                                        ->where('status', 'SUCCESS')->sum('amount');

        $totalDisbursements = Disbursement::where('aggregator_id', $aggregator->id)
                                          ->where('status', 'SUCCESS')->count();
        $totalDisbursed     = Disbursement::where('aggregator_id', $aggregator->id)
                                          ->where('status', 'SUCCESS')->sum('amount');

        $totalBulk        = BulkPayment::where('aggregator_id', $aggregator->id)
                                        ->where('status', 'COMPLETED')->count();
        $totalBulkAmount  = BulkPayment::where('aggregator_id', $aggregator->id)
                                        ->where('status', 'COMPLETED')->sum('total_amount');

        // Total décaissé = disbursements + bulk
        $totalDecaisse = $totalDisbursed + $totalBulkAmount;

        return view('portal.transactions.index', compact(
            'items',
            'total',
            'page',
            'perPage',
            'type',
            'totalCollections',
            'totalAmount',
            'totalDisbursements',
            'totalDisbursed',
            'totalBulk',
            'totalBulkAmount',
            'totalDecaisse',
        ));
    }

    public function show(string $id)
    {
        $aggregator = auth('aggregator')->user();

        // Collection
        $transaction = Transaction::where('transaction_id', $id)
                                  ->where('aggregator_id', $aggregator->id)
                                  ->first();
        if ($transaction) {
            return view('portal.transactions.show', [
                'item' => $transaction,
                'type' => 'COLLECTION',
            ]);
        }

        // Disbursement
        $disbursement = Disbursement::where('disbursement_id', $id)
                                    ->where('aggregator_id', $aggregator->id)
                                    ->first();
        if ($disbursement) {
            return view('portal.transactions.show', [
                'item' => $disbursement,
                'type' => 'DISBURSEMENT',
            ]);
        }

        // Bulk payment
        $bulkPayment = BulkPayment::where('bulk_id', $id)
                                   ->where('aggregator_id', $aggregator->id)
                                   ->with('recipients')
                                   ->firstOrFail();

        return view('portal.transactions.show', [
            'item' => $bulkPayment,
            'type' => 'BULK_PAYMENT',
        ]);
    }
}