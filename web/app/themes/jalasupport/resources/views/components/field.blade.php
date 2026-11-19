@props(['label', 'for', 'error' => null, 'hint' => null])

{{-- Form field wrapper. `error` is an Alpine expression that yields the field's error message. --}}
<div {{ $attributes }}>
  <label for="{{ $for }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
  <div class="mt-1.5">{{ $slot }}</div>
  @if ($hint)
    <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
  @endif
  @if ($error)
    <p class="mt-1 text-xs text-red-600" x-show="{{ $error }}" x-text="{{ $error }}" x-cloak></p>
  @endif
</div>
