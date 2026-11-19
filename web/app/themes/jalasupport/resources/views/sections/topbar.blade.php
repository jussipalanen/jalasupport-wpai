<header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
    <button type="button" class="-m-2 rounded-md p-2 text-slate-500 hover:text-slate-700 lg:hidden" @click="sidebarOpen = true">
        <span class="sr-only">{{ __('Open navigation', 'jalasupport') }}</span>
        <x-icon name="bars" class="size-6" />
    </button>

    <div class="flex-1"></div>

    @if ($currentUser)
        <div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">
            <button type="button" class="flex items-center gap-2 rounded-full p-1 pr-2 text-sm hover:bg-slate-100" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="menu">
                <img src="{{ $currentUser['avatar'] }}" alt="" class="size-8 rounded-full bg-slate-200">
                <span class="hidden font-medium text-slate-700 sm:block">{{ $currentUser['name'] }}</span>
                <x-icon name="chevron-down" class="size-4 text-slate-400" />
            </button>

            <div x-show="open" x-transition @click.outside="open = false" x-cloak role="menu"
                class="absolute right-0 mt-2 w-56 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg">
                <div class="border-b border-slate-100 px-4 py-2">
                    <p class="font-medium text-slate-900">{{ $currentUser['name'] }}</p>
                    <p class="truncate text-slate-500">{{ $currentUser['email'] }}</p>
                </div>
                @if ($currentUser['admin'])
                    <a href="{{ $currentUser['admin'] }}" class="block px-4 py-2 text-slate-700 hover:bg-slate-50" role="menuitem">{{ __('WordPress admin', 'jalasupport') }}</a>
                @endif
                <a href="{{ $currentUser['logout'] }}" class="block px-4 py-2 text-slate-700 hover:bg-slate-50" role="menuitem">{{ __('Log out', 'jalasupport') }}</a>
            </div>
        </div>
    @endif
</header>
