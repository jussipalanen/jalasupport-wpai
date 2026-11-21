<!doctype html>
<html @php(language_attributes()) class="h-full bg-slate-950">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php(do_action('get_header'))
        @php(wp_head())

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body @php(body_class('h-full text-slate-100 antialiased'))>
        @php(wp_body_open())

        <a class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded-md focus:bg-slate-900 focus:px-3 focus:py-2 focus:shadow" href="#main">
            {{ __('Skip to content', 'jalasupport') }}
        </a>

        <script type="application/json" id="jalasupport-config">{!! wp_json_encode($clientConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

        <div x-data="{ sidebarOpen: false }" class="min-h-full">
            @include('sections.sidebar')

            <div class="relative isolate lg:pl-64">
                @include('partials.backdrop', ['variant' => 'banner'])

                @include('sections.topbar')

                <main id="main" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    @unless ($pluginActive)
                        <x-notice type="warning" class="mb-6">
                            {{ __('The JalaSupport plugin is not active. Activate it to manage tickets.', 'jalasupport') }}
                        </x-notice>
                    @endunless

                    @yield('content')
                </main>
            </div>
        </div>

        <x-toast />

        @php(do_action('get_footer'))
        @php(wp_footer())
    </body>
</html>
