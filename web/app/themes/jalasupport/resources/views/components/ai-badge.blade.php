<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full bg-violet-500/10 px-2 py-0.5 text-xs font-medium text-violet-300 ring-1 ring-inset ring-violet-500/30']) }}>
    <x-icon name="sparkles" class="size-3.5" />
    {{ $slot->isEmpty() ? __('AI suggestion', 'jalasupport') : $slot }}
</span>
