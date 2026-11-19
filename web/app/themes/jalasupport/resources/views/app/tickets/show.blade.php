@extends('layouts.app')

@section('content')
    <div x-data="ticketDetail({{ (int) $ticketId }})">
        <template x-if="loading">
            <div class="space-y-4" aria-hidden="true">
                <div class="h-8 w-1/3 animate-pulse rounded bg-slate-200"></div>
                <div class="h-64 animate-pulse rounded-xl bg-slate-100"></div>
            </div>
        </template>

        <template x-if="!loading && notFound">
            <x-card>
                <div class="py-10 text-center">
                    <x-icon name="inbox" class="mx-auto size-10 text-slate-300" />
                    <p class="mt-3 font-medium text-slate-900">{{ __('Ticket not found', 'jalasupport') }}</p>
                    <a href="{{ home_url('/tickets/') }}" class="mt-2 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('Back to tickets', 'jalasupport') }}</a>
                </div>
            </x-card>
        </template>

        <template x-if="!loading && error && !ticket">
            <x-notice type="error"><span x-text="error"></span></x-notice>
        </template>

        <template x-if="ticket">
            <div>
                <x-page-header title="">
                    <x-slot:breadcrumb>
                        <a href="{{ home_url('/tickets/') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
                            <x-icon name="chevron-left" class="size-4" /> {{ __('Tickets', 'jalasupport') }}
                        </a>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm text-slate-400" x-text="'#' + ticket.id"></span>
                            <x-badge kind="status" value="ticket.status" label="ticket.status_label" />
                            <x-badge kind="priority" value="ticket.priority" label="ticket.priority_label" />
                        </div>
                        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900" x-text="ticket.title"></h1>
                    </x-slot:breadcrumb>
                    <x-slot:actions>
                        <x-button variant="secondary" icon="pencil" @click="startEdit()" x-show="!editing">{{ __('Edit', 'jalasupport') }}</x-button>
                        <template x-if="canDelete">
                            <x-button variant="danger" icon="trash" @click="destroy()">{{ __('Delete', 'jalasupport') }}</x-button>
                        </template>
                    </x-slot:actions>
                </x-page-header>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="space-y-6 lg:col-span-2">
                        {{-- Ticket content (user-provided) --}}
                        <x-card :title="__('Customer request', 'jalasupport')">
                            <div x-show="!editing" class="text-sm leading-relaxed whitespace-pre-line text-slate-700" x-text="ticket.description"></div>

                            <form x-show="editing" x-cloak @submit.prevent="saveEdit()" class="space-y-5" novalidate>
                                <x-field :label="__('Subject', 'jalasupport')" for="edit-title" error="errors.title">
                                    <input id="edit-title" type="text" x-model="edit.title" maxlength="200" class="form-input">
                                </x-field>
                                <x-field :label="__('Description', 'jalasupport')" for="edit-description" error="errors.description">
                                    <textarea id="edit-description" rows="8" x-model="edit.description" maxlength="10000" class="form-input"></textarea>
                                </x-field>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                    <x-field :label="__('Customer name', 'jalasupport')" for="edit-name" error="errors.customer_name">
                                        <input id="edit-name" type="text" x-model="edit.customer_name" maxlength="200" class="form-input">
                                    </x-field>
                                    <x-field :label="__('Customer email', 'jalasupport')" for="edit-email" error="errors.customer_email">
                                        <input id="edit-email" type="email" x-model="edit.customer_email" class="form-input">
                                    </x-field>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <x-button variant="secondary" @click="cancelEdit()">{{ __('Cancel', 'jalasupport') }}</x-button>
                                    <x-button type="submit" icon="check" x-bind:disabled="saving">{{ __('Save changes', 'jalasupport') }}</x-button>
                                </div>
                            </form>
                        </x-card>

                        @include('partials.ai-panel')
                    </div>

                    {{-- Sidebar --}}
                    <div class="space-y-6">
                        <x-card :title="__('Properties', 'jalasupport')">
                            <div class="space-y-4">
                                @foreach (['status' => [__('Status', 'jalasupport'), $ticketOptions['statuses']], 'priority' => [__('Priority', 'jalasupport'), $ticketOptions['priorities']], 'category' => [__('Category', 'jalasupport'), $ticketOptions['categories']]] as $key => [$label, $options])
                                    <x-field :label="$label" for="prop-{{ $key }}">
                                        <select id="prop-{{ $key }}" class="form-input" :value="ticket.{{ $key }}" @change="updateField('{{ $key }}', $event.target.value)" x-bind:disabled="saving">
                                            @foreach ($options as $value => $optionLabel)
                                                <option value="{{ $value }}" :selected="ticket.{{ $key }} === @js($value)">{{ $optionLabel }}</option>
                                            @endforeach
                                        </select>
                                    </x-field>
                                @endforeach
                            </div>
                        </x-card>

                        <x-card :title="__('Customer', 'jalasupport')">
                            <div class="flex items-center gap-3">
                                <span class="flex size-10 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                    <x-icon name="user" class="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-900" x-text="ticket.customer.name"></p>
                                    <a class="truncate text-sm text-indigo-600 hover:text-indigo-500" :href="'mailto:' + ticket.customer.email" x-text="ticket.customer.email"></a>
                                </div>
                            </div>
                        </x-card>

                        <x-card :title="__('Details', 'jalasupport')">
                            <dl class="space-y-3 text-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-slate-500">{{ __('Created', 'jalasupport') }}</dt>
                                    <dd class="text-slate-700" x-text="formatDate(ticket.created_at)"></dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-slate-500">{{ __('Last updated', 'jalasupport') }}</dt>
                                    <dd class="text-slate-700" x-text="formatDate(ticket.updated_at)"></dd>
                                </div>
                            </dl>
                        </x-card>
                    </div>
                </div>
            </div>
        </template>
    </div>
@endsection
