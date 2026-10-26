@php
    $pendingCount = \App\Domain\Auth\Models\ProductionRequest::where('status', 'PENDING')->count();
    $tabs = [
        'requests'   => ['Demandes',                route('admin.dashboard')],
        'production' => ['Agrégateurs production',  route('admin.aggregators.index', 'production')],
        'sandbox'    => ['Agrégateurs sandbox',     route('admin.aggregators.index', 'sandbox')],
    ];
@endphp

<div class="flex space-x-6 border-b border-gray-200 mb-8">
    @foreach($tabs as $key => [$label, $url])
        <a href="{{ $url }}"
           class="pb-3 text-sm font-medium border-b-2 -mb-px
                  {{ $active === $key ? '' : 'border-transparent text-gray-500 hover:text-gray-800' }}"
           style="{{ $active === $key ? 'border-color:#b13a7e;color:#b13a7e;' : '' }}">
            {{ $label }}
            @if($key === 'requests' && $pendingCount > 0)
                <span class="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1
                             rounded-full bg-red-600 text-white text-[10px] font-bold">
                    {{ $pendingCount }}
                </span>
            @endif
        </a>
    @endforeach
</div>
