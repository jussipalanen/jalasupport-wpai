{{--
    Public start page: the front page for visitors who are not logged in.
    Shows what the app is, the current ticket totals (aggregate numbers
    only, from the JalaSupport plugin) and the way in for support agents.
--}}
@extends('layouts.public')

@section('content')
    <header class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-6 sm:px-6 lg:px-8">
        <span class="flex size-9 items-center justify-center rounded-lg bg-indigo-500 text-white shadow-sm shadow-indigo-950/40">
            <x-icon name="lifebuoy" class="size-5" />
        </span>
        <span class="text-lg font-semibold tracking-tight text-white">
            JalaSupport <span class="text-indigo-300">AI</span>
        </span>
        <x-button class="ml-auto" variant="secondary" :href="$loginUrl" icon="user">{{ __('Sign in', 'jalasupport') }}</x-button>
    </header>

    <main id="main" class="mx-auto max-w-6xl px-4 pb-16 sm:px-6 lg:px-8">
        {{-- Introduction and the way in --}}
        <section class="mx-auto max-w-3xl py-12 text-center sm:py-16">
            <p class="inline-flex items-center gap-1.5 rounded-full bg-indigo-500/10 px-3 py-1 text-sm font-medium text-indigo-300 ring-1 ring-inset ring-indigo-500/30">
                <x-icon name="sparkles" class="size-4" />
                {{ __('AI-assisted customer support', 'jalasupport') }}
            </p>
            <h1 class="mt-6 text-4xl font-semibold tracking-tight text-white sm:text-5xl">
                {{ __('Support tickets, sorted and answered faster', 'jalasupport') }}
            </h1>
            <p class="mt-6 text-lg text-slate-300">
                {{ __('JalaSupport AI collects customer requests in one queue. Agents track status and priority, and AI suggests a category, a summary and a reply that the agent reviews before anything is saved.', 'jalasupport') }}
            </p>
            <div class="mt-10 flex flex-col items-center gap-3">
                <x-button size="lg" :href="$loginUrl" icon="inbox" class="w-full sm:w-auto">
                    {{ __('Sign in to manage tickets', 'jalasupport') }}
                </x-button>
                <p class="text-sm text-slate-400">{{ __('For support agents. Accounts are created by an administrator.', 'jalasupport') }}</p>
            </div>
        </section>

        @if ($stats)
            <section aria-labelledby="queue-heading">
                <div class="mb-6 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <h2 id="queue-heading" class="text-xl font-semibold text-white">{{ __('Support queue right now', 'jalasupport') }}</h2>
                    <p class="text-sm text-slate-400">{{ __('Totals only, no ticket details. Updated every few minutes.', 'jalasupport') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    {{-- Hero figure: all tickets --}}
                    <x-card>
                        <p class="text-sm font-medium text-slate-400">{{ __('Tickets in total', 'jalasupport') }}</p>
                        <p class="mt-2 text-5xl font-semibold tracking-tight text-white">{{ number_format_i18n($stats['total']) }}</p>
                        <dl class="mt-6 grid grid-cols-2 gap-4">
                            @foreach ($stats['statuses'] as $status)
                                <div>
                                    <dt class="flex items-center gap-1.5 text-sm text-slate-400">
                                        <span class="size-2.5 shrink-0 rounded-sm {{ $statusTones[$status['key']]['fill'] ?? 'bg-slate-500' }}" aria-hidden="true"></span>
                                        {{ $status['label'] }}
                                    </dt>
                                    <dd class="mt-1 text-2xl font-semibold text-white">{{ number_format_i18n($status['count']) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </x-card>

                    {{-- Status breakdown: one whole split into parts --}}
                    <x-card class="lg:col-span-2" :title="__('Tickets by status', 'jalasupport')" :description="__('Share of all tickets in each status.', 'jalasupport')">
                        @if ($stats['total'] > 0)
                            <div class="flex h-7 w-full gap-0.5" role="img"
                                aria-label="{{ collect($stats['statuses'])->map(fn($s) => sprintf('%s: %s (%d%%)', $s['label'], number_format_i18n($s['count']), $s['share']))->implode(', ') }}">
                                @foreach ($stats['statuses'] as $status)
                                    @if ($status['count'] > 0)
                                        @php($tone = $statusTones[$status['key']] ?? ['fill' => 'bg-slate-500', 'ink' => 'text-white'])
                                        <div class="group relative flex min-w-1 items-center rounded px-2 text-xs font-semibold outline-none transition hover:brightness-110 focus-visible:ring-2 focus-visible:ring-white {{ $tone['fill'] }} {{ $tone['ink'] }}"
                                            style="flex: {{ $status['count'] }} 1 0%" tabindex="0">
                                            {{-- Only print the share where it fits. --}}
                                            @if ($status['share'] >= 8)
                                                <span aria-hidden="true">{{ $status['share'] }}%</span>
                                            @endif
                                            <span role="tooltip" class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md border border-white/10 bg-slate-950 px-2.5 py-1.5 text-xs font-normal text-slate-300 opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-visible:opacity-100">
                                                <strong class="font-semibold text-white">{{ number_format_i18n($status['count']) }}</strong>
                                                {{ $status['label'] }} · {{ $status['share'] }}%
                                            </span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                            <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-300">
                                @foreach ($stats['statuses'] as $status)
                                    <li class="flex items-center gap-2">
                                        <span class="size-2.5 rounded-sm {{ $statusTones[$status['key']]['fill'] ?? 'bg-slate-500' }}" aria-hidden="true"></span>
                                        {{ $status['label'] }}
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-slate-400">{{ __('No tickets yet.', 'jalasupport') }}</p>
                        @endif

                        {{-- Resolution rate --}}
                        <div class="mt-8 border-t border-white/10 pt-6">
                            <div class="flex items-baseline justify-between gap-4">
                                <p class="text-sm font-medium text-slate-300" id="resolution-label">{{ __('Resolved or closed', 'jalasupport') }}</p>
                                <p class="text-2xl font-semibold text-white">{{ $stats['resolution_rate'] }}%</p>
                            </div>
                            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-emerald-600/20" role="progressbar"
                                aria-labelledby="resolution-label" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $stats['resolution_rate'] }}">
                                <div class="h-full rounded-full bg-emerald-600" style="width: {{ $stats['resolution_rate'] }}%"></div>
                            </div>
                        </div>
                    </x-card>

                    {{-- Priority (ordered) and category (largest first) --}}
                    @foreach ([
                        ['title' => __('Tickets by priority', 'jalasupport'), 'description' => __('From low to urgent.', 'jalasupport'), 'rows' => $stats['priorities']],
                        ['title' => __('Tickets by category', 'jalasupport'), 'description' => __('Largest first.', 'jalasupport'), 'rows' => $categories],
                    ] as $chart)
                        @php($max = max(array_column($chart['rows'], 'count') ?: [0]))
                        <x-card :class="$loop->first ? 'lg:col-span-1' : 'lg:col-span-2'" :title="$chart['title']" :description="$chart['description']">
                            <dl class="space-y-3">
                                @foreach ($chart['rows'] as $row)
                                    <div class="grid grid-cols-[8rem_1fr] items-center gap-3 sm:grid-cols-[10rem_1fr]">
                                        <dt class="truncate text-sm text-slate-300">{{ $row['label'] }}</dt>
                                        <dd class="flex items-center gap-2">
                                            @if ($row['count'] > 0)
                                                <span class="group relative block h-3 min-w-0.5 rounded-r bg-indigo-500 transition hover:bg-indigo-400"
                                                    style="width: {{ $max > 0 ? round($row['count'] / $max * 85, 1) : 0 }}%">
                                                    <span role="tooltip" class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md border border-white/10 bg-slate-950 px-2.5 py-1.5 text-xs text-slate-300 opacity-0 shadow-lg transition group-hover:opacity-100">
                                                        <strong class="font-semibold text-white">{{ number_format_i18n($row['count']) }}</strong>
                                                        {{ $row['label'] }} · {{ $row['share'] }}%
                                                    </span>
                                                </span>
                                            @endif
                                            <span class="text-sm font-medium text-white">{{ number_format_i18n($row['count']) }}</span>
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </x-card>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- What the app does --}}
        <section class="mt-16 grid grid-cols-1 gap-4 md:grid-cols-3" aria-label="{{ __('Features', 'jalasupport') }}">
            @foreach ([
                ['icon' => 'inbox', 'title' => __('One ticket queue', 'jalasupport'), 'text' => __('Search, filter and sort tickets by status, priority and category.', 'jalasupport')],
                ['icon' => 'sparkles', 'title' => __('AI suggestions', 'jalasupport'), 'text' => __('Category, priority, summary and a reply draft. Nothing is applied without the agent.', 'jalasupport')],
                ['icon' => 'lock', 'title' => __('Private by design', 'jalasupport'), 'text' => __('Tickets are visible only to signed-in agents. This page shows totals only.', 'jalasupport')],
            ] as $feature)
                <div class="rounded-xl border border-white/10 bg-slate-900 p-5">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-300">
                        <x-icon :name="$feature['icon']" class="size-5" />
                    </span>
                    <h3 class="mt-4 text-sm font-semibold text-white">{{ $feature['title'] }}</h3>
                    <p class="mt-1 text-sm text-slate-400">{{ $feature['text'] }}</p>
                </div>
            @endforeach
        </section>
    </main>

    <footer class="mx-auto max-w-6xl border-t border-white/10 px-4 py-6 text-sm text-slate-500 sm:px-6 lg:px-8">
        &copy; {{ wp_date('Y') }} {{ $siteName }}
    </footer>
@endsection
