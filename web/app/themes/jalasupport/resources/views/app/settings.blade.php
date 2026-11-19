@extends('layouts.app')

@section('content')
  <x-page-header :title="__('Settings', 'jalasupport')" :description="__('Application configuration. Values come from environment variables and are read-only here.', 'jalasupport')" />

  <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <x-card class="lg:col-span-2" :title="__('AI assistance (Azure OpenAI)', 'jalasupport')">
      @if ($ai === null)
        <x-notice type="warning">{{ __('The JalaSupport plugin is not active.', 'jalasupport') }}</x-notice>
      @else
        <div class="mb-5 flex items-center gap-3">
          @if ($ai['configured'])
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200 ring-inset">
              <span class="size-1.5 rounded-full bg-emerald-500"></span>{{ __('Configured', 'jalasupport') }}
            </span>
          @else
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200 ring-inset">
              <span class="size-1.5 rounded-full bg-slate-400"></span>{{ __('Not configured', 'jalasupport') }}
            </span>
          @endif
        </div>

        <dl class="divide-y divide-slate-100 text-sm">
          @foreach ([
            __('Endpoint', 'jalasupport') => $ai['endpoint'] ?: '—',
            __('Deployment', 'jalasupport') => $ai['deployment'] ?: '—',
            __('API version', 'jalasupport') => $ai['apiVersion'],
            __('Authentication', 'jalasupport') => $ai['auth'],
          ] as $label => $value)
            <div class="flex flex-col gap-1 py-3 sm:flex-row sm:justify-between">
              <dt class="text-slate-500">{{ $label }}</dt>
              <dd class="font-mono text-slate-800">{{ $value }}</dd>
            </div>
          @endforeach
        </dl>

        @unless ($ai['configured'])
          <x-notice type="info" class="mt-5">
            {{ __('Set AZURE_OPENAI_ENDPOINT, AZURE_OPENAI_DEPLOYMENT and AZURE_OPENAI_API_KEY (or AZURE_OPENAI_USE_MANAGED_IDENTITY=true) in the environment. The application works without AI.', 'jalasupport') }}
          </x-notice>
        @endunless

        <p class="mt-5 flex items-center gap-2 text-xs text-slate-500">
          <x-icon name="lock" class="size-4" />
          {{ __('Credentials are kept on the server and are never shown or sent to the browser.', 'jalasupport') }}
        </p>
      @endif
    </x-card>

    <x-card :title="__('System', 'jalasupport')">
      <dl class="space-y-3 text-sm">
        @foreach ($environment as $label => $value)
          <div>
            <dt class="text-slate-500">{{ $label }}</dt>
            <dd class="break-all text-slate-800">{{ $value }}</dd>
          </div>
        @endforeach
      </dl>
    </x-card>
  </div>
@endsection
