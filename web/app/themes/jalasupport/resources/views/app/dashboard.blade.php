@extends('layouts.app')

@section('content')
  <div x-data="dashboard">
    <x-page-header :title="__('Dashboard', 'jalasupport')" :description="__('Overview of your support queue.', 'jalasupport')">
      <x-slot:actions>
        <x-button :href="home_url('/tickets/new/')" icon="plus">{{ __('New ticket', 'jalasupport') }}</x-button>
      </x-slot:actions>
    </x-page-header>

    <template x-if="error">
      <x-notice type="error" class="mb-6"><span x-text="error"></span></x-notice>
    </template>

    {{-- Stats --}}
    @php
      $stats = [
        ['key' => 'total', 'label' => __('Total tickets', 'jalasupport'), 'icon' => 'ticket', 'tone' => 'bg-slate-100 text-slate-600'],
        ['key' => 'open', 'label' => __('Open', 'jalasupport'), 'icon' => 'inbox', 'tone' => 'bg-sky-50 text-sky-600'],
        ['key' => 'in_progress', 'label' => __('In progress', 'jalasupport'), 'icon' => 'clock', 'tone' => 'bg-amber-50 text-amber-600'],
        ['key' => 'resolved', 'label' => __('Resolved', 'jalasupport'), 'icon' => 'check', 'tone' => 'bg-emerald-50 text-emerald-600'],
      ];
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      @foreach ($stats as $stat)
        <a href="{{ home_url('/tickets/' . ($stat['key'] === 'total' ? '' : '?status=' . $stat['key'])) }}"
           class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">{{ $stat['label'] }}</p>
            <span class="flex size-9 items-center justify-center rounded-lg {{ $stat['tone'] }}">
              <x-icon :name="$stat['icon']" class="size-5" />
            </span>
          </div>
          <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">
            <span x-show="!loading" x-text="stats['{{ $stat['key'] }}'] ?? 0"></span>
            <span x-show="loading" class="inline-block h-8 w-12 animate-pulse rounded bg-slate-100" aria-hidden="true"></span>
          </p>
        </a>
      @endforeach
    </div>

    {{-- Recent activity --}}
    <x-card class="mt-8" :title="__('Recent activity', 'jalasupport')" :description="__('Most recently updated tickets.', 'jalasupport')" :padding="false">
      <x-slot:actions>
        <a href="{{ home_url('/tickets/?orderby=updated_at') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('View all', 'jalasupport') }}</a>
      </x-slot:actions>

      <ul class="divide-y divide-slate-100" aria-live="polite">
        <template x-if="loading">
          <li class="space-y-2 px-5 py-4" aria-hidden="true">
            <div class="h-4 w-1/3 animate-pulse rounded bg-slate-100"></div>
            <div class="h-3 w-1/4 animate-pulse rounded bg-slate-100"></div>
          </li>
        </template>

        <template x-if="!loading && recent.length === 0">
          <li class="px-5 py-10 text-center text-sm text-slate-500">
            {{ __('No tickets yet.', 'jalasupport') }}
            <a href="{{ home_url('/tickets/new/') }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('Create the first one', 'jalasupport') }}</a>
          </li>
        </template>

        <template x-for="ticket in recent" :key="ticket.id">
          <li>
            <a :href="ticketUrl(ticket.id)" class="flex flex-col gap-2 px-5 py-4 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-slate-900" x-text="ticket.title"></p>
                <p class="mt-0.5 truncate text-xs text-slate-500">
                  <span x-text="'#' + ticket.id"></span> ·
                  <span x-text="ticket.customer.name"></span> ·
                  <span x-text="ticket.category_label"></span>
                </p>
              </div>
              <div class="flex shrink-0 items-center gap-2">
                <x-badge kind="priority" value="ticket.priority" label="ticket.priority_label" />
                <x-badge kind="status" value="ticket.status" label="ticket.status_label" />
                <span class="w-24 text-right text-xs text-slate-400" x-text="relativeTime(ticket.updated_at)"></span>
              </div>
            </a>
          </li>
        </template>
      </ul>
    </x-card>
  </div>
@endsection
