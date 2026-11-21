@extends('layouts.app')

@section('content')
    <div x-data="ticketForm">
        <x-page-header :title="__('New ticket', 'jalasupport')" :description="__('Log a customer request.', 'jalasupport')">
            <x-slot:breadcrumb>
                <a href="{{ home_url('/tickets/') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-slate-300 hover:text-white">
                    <x-icon name="chevron-left" class="size-4" /> {{ __('Tickets', 'jalasupport') }}
                </a>
            </x-slot:breadcrumb>
        </x-page-header>

        <form @submit.prevent="submit()" novalidate class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card class="lg:col-span-2" :title="__('Request', 'jalasupport')">
                <div class="space-y-5">
                    <x-field :label="__('Subject', 'jalasupport')" for="title" error="errors.title">
                        <input id="title" type="text" x-model="form.title" required maxlength="200" class="form-input" :aria-invalid="!!errors.title">
                    </x-field>

                    <x-field :label="__('Description', 'jalasupport')" for="description" error="errors.description" :hint="__('Describe the issue in the customer\'s words.', 'jalasupport')">
                        <textarea id="description" rows="10" x-model="form.description" required maxlength="10000" class="form-input" :aria-invalid="!!errors.description"></textarea>
                    </x-field>
                </div>
            </x-card>

            <div class="space-y-6">
                <x-card :title="__('Customer', 'jalasupport')">
                    <div class="space-y-5">
                        <x-field :label="__('Name', 'jalasupport')" for="customer_name" error="errors.customer_name">
                            <input id="customer_name" type="text" x-model="form.customer_name" required maxlength="200" autocomplete="off" class="form-input" :aria-invalid="!!errors.customer_name">
                        </x-field>

                        <x-field :label="__('Email', 'jalasupport')" for="customer_email" error="errors.customer_email">
                            <input id="customer_email" type="email" x-model="form.customer_email" required autocomplete="off" class="form-input" :aria-invalid="!!errors.customer_email">
                        </x-field>
                    </div>
                </x-card>

                <x-card :title="__('Classification', 'jalasupport')" :description="__('You can ask AI for suggestions after creating the ticket.', 'jalasupport')">
                    <div class="space-y-5">
                        <x-field :label="__('Category', 'jalasupport')" for="category" error="errors.category">
                            <select id="category" x-model="form.category" class="form-input">
                                @foreach ($ticketOptions['categories'] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-field>

                        <x-field :label="__('Priority', 'jalasupport')" for="priority" error="errors.priority">
                            <select id="priority" x-model="form.priority" class="form-input">
                                @foreach ($ticketOptions['priorities'] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-field>
                    </div>
                </x-card>

                <template x-if="error">
                    <x-notice type="error"><span x-text="error"></span></x-notice>
                </template>

                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="home_url('/tickets/')">{{ __('Cancel', 'jalasupport') }}</x-button>
                    <x-button type="submit" icon="check" x-bind:disabled="saving">
                        <span x-text="saving ? @js(__('Creating…', 'jalasupport')) : @js(__('Create ticket', 'jalasupport'))"></span>
                    </x-button>
                </div>
            </div>
        </form>
    </div>
@endsection
