{{-- Global toast; components dispatch window events: $dispatch('toast', { message, type }) --}}
<div
  x-data="toast"
  @toast.window="show($event.detail)"
  class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex justify-center p-4 sm:justify-end"
  aria-live="polite"
>
  <div
    x-show="visible"
    x-transition
    x-cloak
    class="pointer-events-auto flex max-w-sm items-center gap-3 rounded-lg px-4 py-3 text-sm shadow-lg ring-1"
    :class="type === 'error' ? 'bg-red-50 text-red-800 ring-red-200' : 'bg-slate-900 text-white ring-slate-900'"
  >
    <span x-text="message"></span>
    <button type="button" class="opacity-70 hover:opacity-100" @click="visible = false">
      <span class="sr-only">{{ __('Dismiss', 'jalasupport') }}</span>
      <x-icon name="x" class="size-4" />
    </button>
  </div>
</div>
