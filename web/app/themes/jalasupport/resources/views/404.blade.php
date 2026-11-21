@extends('layouts.app')

@section('content')
    <x-card class="mx-auto max-w-lg">
        <div class="py-8 text-center">
            <p class="text-sm font-semibold text-indigo-400">404</p>
            <h1 class="mt-2 text-lg font-semibold text-white">{{ __('Page not found', 'jalasupport') }}</h1>
            <p class="mt-2 text-sm text-slate-400">{{ __('The page you are looking for does not exist.', 'jalasupport') }}</p>
            <x-button class="mt-6" :href="home_url('/')">{{ __('Go to dashboard', 'jalasupport') }}</x-button>
        </div>
    </x-card>
@endsection
