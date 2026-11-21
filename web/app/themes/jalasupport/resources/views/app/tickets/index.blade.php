@extends('layouts.app')

@section('content')
    <div x-data="ticketList">
        <x-page-header :title="__('Tickets', 'jalasupport')" :description="__('Search, filter and manage support tickets.', 'jalasupport')">
            <x-slot:actions>
                <x-button :href="home_url('/tickets/new/')" icon="plus">{{ __('New ticket', 'jalasupport') }}</x-button>
            </x-slot:actions>
        </x-page-header>

        {{-- Filters --}}
        <form class="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-white/10 bg-slate-900 p-4 shadow-sm md:grid-cols-2 xl:grid-cols-6" @submit.prevent="apply()" role="search">
            <div class="relative md:col-span-2">
                <label for="ticket-search" class="sr-only">{{ __('Search tickets', 'jalasupport') }}</label>
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-slate-400" />
                <input id="ticket-search" type="search" x-model="filters.search" @input.debounce.400ms="apply()" maxlength="200"
                    placeholder="{{ __('Search title or description…', 'jalasupport') }}" class="form-input pl-9">
            </div>

            @foreach (['status' => [__('Status', 'jalasupport'), $ticketOptions['statuses'], __('All statuses', 'jalasupport')], 'priority' => [__('Priority', 'jalasupport'), $ticketOptions['priorities'], __('All priorities', 'jalasupport')], 'category' => [__('Category', 'jalasupport'), $ticketOptions['categories'], __('All categories', 'jalasupport')]] as $key => [$label, $options, $all])
                <div>
                    <label for="filter-{{ $key }}" class="sr-only">{{ $label }}</label>
                    <select id="filter-{{ $key }}" x-model="filters.{{ $key }}" @change="apply()" class="form-input">
                        <option value="">{{ $all }}</option>
                        @foreach ($options as $value => $optionLabel)
                            <option value="{{ $value }}">{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach

            <div>
                <label for="filter-sort" class="sr-only">{{ __('Sort by', 'jalasupport') }}</label>
                <select id="filter-sort" x-model="sort" @change="apply()" class="form-input">
                    <option value="created_at:desc">{{ __('Newest first', 'jalasupport') }}</option>
                    <option value="created_at:asc">{{ __('Oldest first', 'jalasupport') }}</option>
                    <option value="updated_at:desc">{{ __('Recently updated', 'jalasupport') }}</option>
                    <option value="title:asc">{{ __('Title A–Z', 'jalasupport') }}</option>
                    <option value="title:desc">{{ __('Title Z–A', 'jalasupport') }}</option>
                </select>
            </div>
        </form>

        <div class="mb-3 flex items-center justify-between text-sm text-slate-400">
            <p aria-live="polite">
                <span x-show="!loading" x-text="summary()"></span>
                <span x-show="loading">{{ __('Loading…', 'jalasupport') }}</span>
            </p>
            <button type="button" class="text-sm font-medium text-indigo-400 hover:text-indigo-300" x-show="hasFilters()" @click="reset()" x-cloak>
                {{ __('Clear filters', 'jalasupport') }}
            </button>
        </div>

        <template x-if="error">
            <x-notice type="error" class="mb-4"><span x-text="error"></span></x-notice>
        </template>

        {{-- Table (md and up) / cards (mobile) --}}
        <div class="overflow-hidden rounded-xl border border-white/10 bg-slate-900 shadow-sm">
            <table class="hidden min-w-full divide-y divide-white/10 md:table">
                <thead class="bg-white/5 text-left text-xs font-medium tracking-wide text-slate-400 uppercase">
                    <tr>
                        <th scope="col" class="px-5 py-3">{{ __('Ticket', 'jalasupport') }}</th>
                        <th scope="col" class="px-5 py-3">{{ __('Customer', 'jalasupport') }}</th>
                        <th scope="col" class="px-5 py-3">{{ __('Category', 'jalasupport') }}</th>
                        <th scope="col" class="px-5 py-3">{{ __('Priority', 'jalasupport') }}</th>
                        <th scope="col" class="px-5 py-3">{{ __('Status', 'jalasupport') }}</th>
                        <th scope="col" class="px-5 py-3 text-right">{{ __('Updated', 'jalasupport') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-sm" :class="{ 'opacity-60': loading }">
                    <template x-for="ticket in tickets" :key="ticket.id">
                        <tr class="cursor-pointer hover:bg-white/5" @click="open(ticket.id)">
                            <td class="max-w-xs px-5 py-3">
                                <a :href="ticketUrl(ticket.id)" class="block truncate font-medium text-white hover:text-indigo-300" x-text="ticket.title" @click.stop></a>
                                <span class="text-xs text-slate-400" x-text="'#' + ticket.id"></span>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-slate-200" x-text="ticket.customer.name"></p>
                                <p class="text-xs text-slate-400" x-text="ticket.customer.email"></p>
                            </td>
                            <td class="px-5 py-3 text-slate-300" x-text="ticket.category_label"></td>
                            <td class="px-5 py-3"><x-badge kind="priority" value="ticket.priority" label="ticket.priority_label" /></td>
                            <td class="px-5 py-3"><x-badge kind="status" value="ticket.status" label="ticket.status_label" /></td>
                            <td class="px-5 py-3 text-right text-xs whitespace-nowrap text-slate-400" x-text="relativeTime(ticket.updated_at)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <ul class="divide-y divide-white/5 md:hidden">
                <template x-for="ticket in tickets" :key="ticket.id">
                    <li>
                        <a :href="ticketUrl(ticket.id)" class="block space-y-2 px-4 py-4 hover:bg-white/5">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-medium text-white" x-text="ticket.title"></p>
                                <x-badge kind="status" value="ticket.status" label="ticket.status_label" />
                            </div>
                            <p class="text-xs text-slate-400">
                                <span x-text="'#' + ticket.id"></span> · <span x-text="ticket.customer.name"></span> · <span x-text="relativeTime(ticket.updated_at)"></span>
                            </p>
                            <x-badge kind="priority" value="ticket.priority" label="ticket.priority_label" />
                        </a>
                    </li>
                </template>
            </ul>

            <template x-if="!loading && tickets.length === 0 && !error">
                <div class="px-5 py-16 text-center">
                    <x-icon name="inbox" class="mx-auto size-10 text-slate-300" />
                    <p class="mt-3 text-sm font-medium text-white">{{ __('No tickets found', 'jalasupport') }}</p>
                    <p class="mt-1 text-sm text-slate-400">{{ __('Try adjusting your search or filters.', 'jalasupport') }}</p>
                </div>
            </template>
        </div>

        {{-- Pagination and page size --}}
        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between" x-show="meta.total > 0" x-cloak>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-400">
                <p aria-live="polite" x-text="rangeLabel()"></p>

                <label class="flex items-center gap-2">
                    <span>{{ __('Rows per page', 'jalasupport') }}</span>
                    <select class="form-input w-auto py-1.5 pr-8" :value="perPage" @change="setPerPage($event.target.value)" x-bind:disabled="loading">
                        <template x-for="option in perPageOptions" :key="option">
                            <option :value="option" :selected="option === perPage" x-text="option"></option>
                        </template>
                    </select>
                </label>
            </div>

            <nav class="flex items-center gap-1" x-show="meta.pages > 1" aria-label="{{ __('Pagination', 'jalasupport') }}">
                <x-button variant="secondary" class="px-2.5" @click="go(meta.page - 1)" x-bind:disabled="meta.page <= 1 || loading">
                    <x-icon name="chevron-left" class="size-4" />
                    <span class="sr-only sm:not-sr-only">{{ __('Previous', 'jalasupport') }}</span>
                </x-button>

                <template x-for="(page, index) in pages()" :key="index">
                    <span>
                        <span x-show="page === null" class="px-1.5 text-sm text-slate-500" aria-hidden="true">&hellip;</span>
                        <button
                            type="button"
                            x-show="page !== null"
                            class="min-w-9 rounded-md px-2.5 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 disabled:cursor-not-allowed"
                            :class="page === meta.page ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-300 ring-1 ring-inset ring-white/10 hover:bg-white/10'"
                            :aria-current="page === meta.page ? 'page' : null"
                            :aria-label="@js(__('Page', 'jalasupport')) + ' ' + page"
                            @click="go(page)"
                            x-bind:disabled="loading"
                            x-text="page"
                        ></button>
                    </span>
                </template>

                <x-button variant="secondary" class="px-2.5" @click="go(meta.page + 1)" x-bind:disabled="meta.page >= meta.pages || loading">
                    <span class="sr-only sm:not-sr-only">{{ __('Next', 'jalasupport') }}</span>
                    <x-icon name="chevron-right" class="size-4" />
                </x-button>
            </nav>
        </div>
    </div>
@endsection
