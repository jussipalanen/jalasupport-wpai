@props(['title', 'description' => null])

<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
  <div>
    {{ $breadcrumb ?? '' }}
    @if ($title !== '')
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $title }}</h1>
    @endif
    @if ($description)
      <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
    @endif
  </div>

  @isset($actions)
    <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
  @endisset
</div>
