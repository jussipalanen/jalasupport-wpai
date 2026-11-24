@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'icon' => null])

@php
    $classes = [
        'primary' => 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-indigo-600',
        'secondary' => 'bg-white/5 text-slate-200 ring-1 ring-inset ring-white/15 shadow-sm hover:bg-white/10 focus-visible:outline-slate-400',
        'ghost' => 'text-slate-300 hover:bg-white/10 hover:text-white focus-visible:outline-slate-400',
        'danger' => 'bg-red-500/10 text-red-300 ring-1 ring-inset ring-red-500/30 hover:bg-red-500/20 focus-visible:outline-red-500',
        'ai' => 'bg-violet-600 text-white shadow-sm hover:bg-violet-500 focus-visible:outline-violet-600',
    ][$variant] ?? '';

    $sizes = [
        'md' => 'gap-1.5 rounded-md px-3 py-2 text-sm',
        'lg' => 'gap-2 rounded-lg px-6 py-3 text-base',
    ][$size] ?? '';
    $iconSize = $size === 'lg' ? 'size-5' : 'size-4';

    $base = "inline-flex items-center justify-center $sizes font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50";
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => "$base $classes"]) }}>
        @if ($icon) <x-icon :name="$icon" :class="$iconSize" /> @endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => "$base $classes"]) }}>
        @if ($icon) <x-icon :name="$icon" :class="$iconSize" /> @endif
        {{ $slot }}
    </button>
@endif
