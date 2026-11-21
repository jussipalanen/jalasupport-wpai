@props(['title' => null, 'description' => null, 'padding' => true])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-white/10 bg-slate-900 shadow-sm']) }}>
    @if ($title || isset($actions))
        <header class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
            <div>
                @if ($title)
                    <h2 class="text-sm font-semibold text-white">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-slate-400">{{ $description }}</p>
                @endif
            </div>
            {{ $actions ?? '' }}
        </header>
    @endif

    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
</section>
