<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700 ring-1 ring-inset ring-violet-200']) }}>
  <x-icon name="sparkles" class="size-3.5" />
  {{ $slot->isEmpty() ? __('AI suggestion', 'jalasupport') : $slot }}
</span>
