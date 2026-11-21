{{-- Fallback for WordPress requests outside the application routes. --}}
@extends('layouts.app')

@section('content')
    <x-card class="mx-auto max-w-lg">
        <div class="py-8 text-center">
            <h1 class="text-lg font-semibold text-white">{{ $siteName }}</h1>
            <p class="mt-2 text-sm text-slate-400">{{ __('This site is the JalaSupport support application.', 'jalasupport') }}</p>
            <x-button class="mt-6" :href="home_url('/')">{{ __('Go to dashboard', 'jalasupport') }}</x-button>
        </div>
    </x-card>
@endsection
