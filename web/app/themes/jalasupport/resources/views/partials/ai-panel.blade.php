{{--
    AI assistance panel (inside ticketDetail). AI output is visually separated
    (violet, "AI suggestion" badge) and is only saved after the user accepts it.
--}}
<section class="rounded-xl border border-violet-500/30 bg-gradient-to-b from-violet-500/10 to-slate-900 shadow-sm" aria-labelledby="ai-panel-title">
    <header class="flex flex-col gap-3 border-b border-violet-500/20 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 id="ai-panel-title" class="flex items-center gap-2 text-sm font-semibold text-white">
                <x-icon name="sparkles" class="size-4 text-violet-400" /> {{ __('AI assistant', 'jalasupport') }}
            </h2>
            <p class="mt-0.5 text-sm text-slate-400">{{ __('Suggestions are not saved until you accept them.', 'jalasupport') }}</p>
        </div>
        <div class="flex gap-2">
            <x-button variant="ai" icon="sparkles" @click="analyze()" x-bind:disabled="ai.loading">
                <span x-text="ai.loading === 'analyze' ? @js(__('Analyzing…', 'jalasupport')) : @js(__('Analyze', 'jalasupport'))"></span>
            </x-button>
            <x-button variant="secondary" icon="pencil" @click="suggestReply()" x-bind:disabled="ai.loading">
                <span x-text="ai.loading === 'reply' ? @js(__('Writing…', 'jalasupport')) : @js(__('Suggest reply', 'jalasupport'))"></span>
            </x-button>
        </div>
    </header>

    <div class="space-y-5 p-5">
        <template x-if="ai.error">
            <x-notice type="warning"><span x-text="ai.error"></span></x-notice>
        </template>

        {{-- Pending suggestions --}}
        <template x-if="ai.suggestions">
            <div class="space-y-4">
                <template x-if="ai.suggestions.category || ai.suggestions.priority">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <template x-for="field in ['category', 'priority']" :key="field">
                            <div x-show="ai.suggestions[field]" class="rounded-lg border border-violet-500/30 bg-slate-900 p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-medium tracking-wide text-slate-400 uppercase" x-text="field === 'category' ? @js(__('Category', 'jalasupport')) : @js(__('Priority', 'jalasupport'))"></p>
                                    <x-ai-badge />
                                </div>
                                <p class="mt-2 text-sm text-slate-200">
                                    <span class="text-slate-400 line-through" x-show="ticket[field] !== ai.suggestions[field]" x-text="ticket[field + '_label']"></span>
                                    <span class="font-medium text-violet-300" x-text="ai.suggestions[field + '_label']"></span>
                                    <span class="text-xs text-slate-400" x-show="ticket[field] === ai.suggestions[field]">{{ __('(matches current)', 'jalasupport') }}</span>
                                </p>
                                <div class="mt-3 flex gap-2" x-show="ticket[field] !== ai.suggestions[field]">
                                    <x-button variant="ai" icon="check" class="px-2 py-1 text-xs" @click="acceptField(field)" x-bind:disabled="saving">{{ __('Accept', 'jalasupport') }}</x-button>
                                    <x-button variant="ghost" icon="x" class="px-2 py-1 text-xs" @click="rejectSuggestion(field)">{{ __('Reject', 'jalasupport') }}</x-button>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="ai.suggestions.summary !== undefined">
                    <div class="rounded-lg border border-violet-500/30 bg-slate-900 p-4">
                        <div class="flex items-center justify-between">
                            <label for="ai-summary" class="text-xs font-medium tracking-wide text-slate-400 uppercase">{{ __('Summary', 'jalasupport') }}</label>
                            <x-ai-badge />
                        </div>
                        <textarea id="ai-summary" rows="3" class="form-input mt-2" x-model="ai.suggestions.summary" maxlength="5000"></textarea>
                        <div class="mt-3 flex gap-2">
                            <x-button variant="ai" icon="check" class="px-2 py-1 text-xs" @click="acceptText('summary')" x-bind:disabled="saving">{{ __('Accept and save', 'jalasupport') }}</x-button>
                            <x-button variant="ghost" icon="x" class="px-2 py-1 text-xs" @click="rejectSuggestion('summary')">{{ __('Reject', 'jalasupport') }}</x-button>
                        </div>
                    </div>
                </template>

                <template x-if="ai.suggestions.suggested_reply !== undefined">
                    <div class="rounded-lg border border-violet-500/30 bg-slate-900 p-4">
                        <div class="flex items-center justify-between">
                            <label for="ai-reply" class="text-xs font-medium tracking-wide text-slate-400 uppercase">{{ __('Suggested reply', 'jalasupport') }}</label>
                            <x-ai-badge />
                        </div>
                        <textarea id="ai-reply" rows="8" class="form-input mt-2" x-model="ai.suggestions.suggested_reply" maxlength="5000"></textarea>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-button variant="ai" icon="check" class="px-2 py-1 text-xs" @click="acceptText('suggested_reply')" x-bind:disabled="saving">{{ __('Accept and save', 'jalasupport') }}</x-button>
                            <x-button variant="secondary" class="px-2 py-1 text-xs" @click="copy(ai.suggestions.suggested_reply)">{{ __('Copy', 'jalasupport') }}</x-button>
                            <x-button variant="ghost" icon="x" class="px-2 py-1 text-xs" @click="rejectSuggestion('suggested_reply')">{{ __('Reject', 'jalasupport') }}</x-button>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- Saved (previously accepted) AI content --}}
        <template x-if="ticket.ai.summary || ticket.ai.suggested_reply">
            <div class="space-y-4 border-t border-violet-500/20 pt-5">
                <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">{{ __('Saved from AI (accepted by an agent)', 'jalasupport') }}</p>
                <div x-show="ticket.ai.summary">
                    <p class="text-sm font-medium text-slate-200">{{ __('Summary', 'jalasupport') }}</p>
                    <p class="mt-1 text-sm whitespace-pre-line text-slate-300" x-text="ticket.ai.summary"></p>
                </div>
                <div x-show="ticket.ai.suggested_reply">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-200">{{ __('Reply draft', 'jalasupport') }}</p>
                        <button type="button" class="text-xs font-medium text-indigo-400 hover:text-indigo-300" @click="copy(ticket.ai.suggested_reply)">{{ __('Copy', 'jalasupport') }}</button>
                    </div>
                    <p class="mt-1 text-sm whitespace-pre-line text-slate-300" x-text="ticket.ai.suggested_reply"></p>
                </div>
            </div>
        </template>

        <template x-if="!ai.suggestions && !ai.error && !ticket.ai.summary && !ticket.ai.suggested_reply">
            <p class="text-sm text-slate-400">{{ __('Use Analyze to get a suggested category, priority, summary and reply.', 'jalasupport') }}</p>
        </template>
    </div>
</section>
