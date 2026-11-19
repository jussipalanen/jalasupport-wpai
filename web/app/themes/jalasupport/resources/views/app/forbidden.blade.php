@extends('layouts.app')

@section('content')
    <x-card class="mx-auto max-w-lg">
        <div class="py-8 text-center">
            <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                <x-icon name="lock" class="size-6" />
            </span>
            <h1 class="mt-4 text-lg font-semibold text-slate-900">{{ __('Access restricted', 'jalasupport') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('Your account does not have access to this part of JalaSupport. Ask an administrator to grant you the Support Agent role.', 'jalasupport') }}</p>
        </div>
    </x-card>
@endsection
