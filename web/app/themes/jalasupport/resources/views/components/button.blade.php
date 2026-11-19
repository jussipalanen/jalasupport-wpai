@props(['variant' => 'primary', 'href' => null, 'icon' => null])

@php
  $classes = [
    'primary' => 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-indigo-600',
    'secondary' => 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 shadow-sm hover:bg-slate-50 focus-visible:outline-slate-400',
    'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-slate-400',
    'danger' => 'bg-white text-red-600 ring-1 ring-inset ring-red-200 hover:bg-red-50 focus-visible:outline-red-500',
    'ai' => 'bg-violet-600 text-white shadow-sm hover:bg-violet-500 focus-visible:outline-violet-600',
  ][$variant] ?? '';

  $base = 'inline-flex items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
@endphp

@if ($href)
  <a href="{{ $href }}" {{ $attributes->merge(['class' => "$base $classes"]) }}>
    @if ($icon) <x-icon :name="$icon" class="size-4" /> @endif
    {{ $slot }}
  </a>
@else
  <button {{ $attributes->merge(['type' => 'button', 'class' => "$base $classes"]) }}>
    @if ($icon) <x-icon :name="$icon" class="size-4" /> @endif
    {{ $slot }}
  </button>
@endif
