@props(['type' => 'info'])

@php
  $styles = [
    'info' => 'border-sky-200 bg-sky-50 text-sky-800',
    'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
    'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
    'error' => 'border-red-200 bg-red-50 text-red-800',
  ][$type] ?? '';
@endphp

<div {{ $attributes->merge(['class' => "flex gap-3 rounded-lg border px-4 py-3 text-sm $styles", 'role' => $type === 'error' ? 'alert' : 'status']) }}>
  <x-icon name="exclamation" class="mt-0.5 size-4 shrink-0" />
  <div>{{ $slot }}</div>
</div>
