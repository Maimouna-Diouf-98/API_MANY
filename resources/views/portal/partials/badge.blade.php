@php
    $s = strtoupper((string) $status);
    $map = [
        'SUCCESS' => ['green', 'Réussi'],   'COMPLETED' => ['green', 'Terminé'],
        'ACTIVE'  => ['green', 'Actif'],    'PRODUCTION_ACTIVE' => ['green', 'Actif'], 'SANDBOX_ACTIVE' => ['green', 'Actif'],
        'PENDING' => ['amber', 'En attente'], 'PROCESSING' => ['blue', 'En cours'],
        'FAILED'  => ['red', 'Échoué'],     'REFUNDED' => ['slate', 'Remboursé'],
        'SUSPENDED' => ['orange', 'Suspendu'], 'CLOSED' => ['slate', 'Clôturé'],
    ];
    [$c, $label] = $map[$s] ?? ['slate', $s];
    $ring = [
        'green'  => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'amber'  => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'blue'   => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'red'    => 'bg-red-50 text-red-700 ring-red-600/20',
        'orange' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
        'slate'  => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    ];
    $dot = [
        'green' => 'bg-emerald-500', 'amber' => 'bg-amber-500', 'blue' => 'bg-blue-500',
        'red' => 'bg-red-500', 'orange' => 'bg-orange-500', 'slate' => 'bg-slate-400',
    ];
@endphp
<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $ring[$c] }}">
    <span class="h-1.5 w-1.5 rounded-full {{ $dot[$c] }}"></span>{{ $label }}
</span>
