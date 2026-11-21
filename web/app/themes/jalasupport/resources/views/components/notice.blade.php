@props(['type' => 'info'])

@php
    $styles = [
        'info' => 'border-sky-500/30 bg-sky-500/10 text-sky-200',
        'success' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200',
        'warning' => 'border-amber-500/30 bg-amber-500/10 text-amber-200',
        'error' => 'border-red-500/30 bg-red-500/10 text-red-200',
    ][$type] ?? '';
@endphp

<div {{ $attributes->merge(['class' => "flex gap-3 rounded-lg border px-4 py-3 text-sm $styles", 'role' => $type === 'error' ? 'alert' : 'status']) }}>
    <x-icon name="exclamation" class="mt-0.5 size-4 shrink-0" />
    <div>{{ $slot }}</div>
</div>
