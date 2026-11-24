<!doctype html>
<html @php(language_attributes()) class="h-full bg-slate-950">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php(do_action('get_header'))
        @php(wp_head())

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body @php(body_class('min-h-full text-slate-100 antialiased'))>
        @php(wp_body_open())

        <a class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded-md focus:bg-slate-900 focus:px-3 focus:py-2 focus:shadow" href="#main">
            {{ __('Skip to content', 'jalasupport') }}
        </a>

        @include('partials.backdrop')

        <div class="relative isolate min-h-full">
            @yield('content')
        </div>

        @php(do_action('get_footer'))
        @php(wp_footer())
    </body>
</html>
