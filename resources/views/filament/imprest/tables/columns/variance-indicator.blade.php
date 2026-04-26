@php
    $severity = $getSeverity();
    $colors = [
        'negligible' => 'bg-green-100 text-green-800',
        'minor' => 'bg-blue-100 text-blue-800',
        'moderate' => 'bg-yellow-100 text-yellow-800',
        'critical' => 'bg-red-100 text-red-800',
    ];
@endphp

<div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $colors[$severity] }}">
    {{ ucfirst($severity) }}
    @if($severity === 'critical')
        <x-heroicon-m-exclamation-triangle class="w-3 h-3 ml-1" />
    @endif
</div>
