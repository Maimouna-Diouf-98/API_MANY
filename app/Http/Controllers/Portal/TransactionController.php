<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Auth\Services\PortalDataService;
use App\Support\CheckoutLinks;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    private const MAX_EXPORT_ROWS = 5000;

    public function __construct(protected PortalDataService $data) {}

    private function ctx(): array
    {
        return $this->data->context(auth('aggregator')->user());
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'type'            => 'nullable|in:collections,disbursements,bulks',
            'status'          => 'nullable|in:PENDING,PROCESSING,SUCCESS,COMPLETED,FAILED,REFUNDED',
            'q'               => 'nullable|string|max:100',
            'from'            => 'nullable|date',
            'to'              => 'nullable|date',
            'sub_merchant_id' => 'nullable|string|max:36',
        ]);
    }

    public function index(Request $request)
    {
        $ctx     = $this->ctx();
        $filters = $this->filters($request);
        $type    = $filters['type'] ?? 'collections';

        $items = $this->data->transactions($ctx, $type, $filters);

        return view('portal.transactions.index', [
            'env'          => $ctx['env'],
            'type'         => $type,
            'filters'      => $filters,
            'items'        => $items,
            'names'        => $this->data->subMerchantNames($ctx, $items->pluck('sub_merchant_id')),
            'subMerchants' => $this->data->allSubMerchants($ctx),
        ]);
    }

    public function show(Request $request, string $transactionId)
    {
        $ctx   = $this->ctx();
        $found = $this->data->findTransaction($ctx, $transactionId, $request->query('type'));

        abort_if(!$found, 404);

        $row = $found['row'];

        // Collection en attente et encore dans son délai : lien vers la page de paiement (QR à scanner)
        $checkoutUrl = null;
        if ($found['type'] === 'collections' && strtoupper($row->status) === 'PENDING') {
            $createdAt = \Carbon\Carbon::parse($row->created_at);
            if ($createdAt->copy()->addMinutes(CheckoutLinks::PAYMENT_WINDOW_MINUTES)->isFuture()) {
                $checkoutUrl = CheckoutLinks::url($ctx['env'], $row->transaction_id, $createdAt);
            }
        }

        return view('portal.transactions.show', [
            'env'        => $ctx['env'],
            'checkoutUrl' => $checkoutUrl,
            'type'       => $found['type'],
            'row'        => $row,
            'merchant'   => $this->data->subMerchantNames($ctx, [$row->sub_merchant_id])[$row->sub_merchant_id] ?? '—',
            'recipients' => $found['type'] === 'bulks'
                            ? $this->data->bulkRecipients($ctx, $row->bulk_id)
                            : collect(),
        ]);
    }

    /** Export CSV des résultats filtrés (5 000 lignes maximum, séparateur « ; » pour Excel). */
    public function export(Request $request)
    {
        $ctx     = $this->ctx();
        $filters = $this->filters($request);
        $type    = $filters['type'] ?? 'collections';

        $rows    = $this->data->transactionsQuery($ctx, $type, $filters)->limit(self::MAX_EXPORT_ROWS)->get();
        $columns = $this->data->columns($ctx, $type);

        $filename = "many-{$ctx['env']}-{$type}-" . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");               // BOM : accents corrects dans Excel
            fputcsv($out, $columns, ';');

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($c) => $this->csvSafe($row->{$c} ?? ''), $columns), ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise les cellules interprétées comme des formules par Excel. */
    private function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) ? "'" . $value : $value;
    }
}
