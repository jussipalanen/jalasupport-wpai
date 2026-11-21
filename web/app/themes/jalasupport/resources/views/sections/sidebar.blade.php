{{-- Mobile overlay --}}
<div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/40 lg:hidden" @click="sidebarOpen = false" x-cloak></div>

<aside
    class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-white/10 bg-slate-900 transition-transform duration-200 lg:translate-x-0"
    :class="{ 'translate-x-0': sidebarOpen }"
    aria-label="{{ __('Main navigation', 'jalasupport') }}"
>
    <div class="flex h-16 items-center gap-2 border-b border-white/10 px-6">
        <span class="flex size-8 items-center justify-center rounded-lg bg-indigo-500 text-white shadow-sm shadow-indigo-950/40">
            <x-icon name="lifebuoy" class="size-5" />
        </span>
        <a href="{{ home_url('/') }}" class="text-base font-semibold tracking-tight text-white">
            JalaSupport <span class="text-indigo-300">AI</span>
        </a>
        <button type="button" class="ml-auto rounded-md p-1 text-slate-400 hover:text-white lg:hidden" @click="sidebarOpen = false">
            <span class="sr-only">{{ __('Close navigation', 'jalasupport') }}</span>
            <x-icon name="x" class="size-5" />
        </button>
    </div>

    <nav class="flex-1 space-y-1 px-3 py-4">
        @foreach ($navigation as $item)
            <a
                href="{{ $item['url'] }}"
                @class([
                    'group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                    'bg-white/10 text-white' => $item['active'],
                    'text-slate-300 hover:bg-white/5 hover:text-white' => ! $item['active'],
                ])
                @if ($item['active']) aria-current="page" @endif
            >
                <x-icon :name="$item['icon']" @class(['size-5', 'text-indigo-300' => $item['active'], 'text-slate-400 group-hover:text-slate-200' => ! $item['active']]) />
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="border-t border-white/10 p-4 text-xs leading-relaxed text-slate-400">
        {{ __('AI suggestions are always reviewed by a person before they are saved.', 'jalasupport') }}
    </div>
</aside>
