{{--
    Status / priority badge rendered by Alpine. `kind` is "status" or "priority";
    `value` and `label` are Alpine expressions.
--}}
@props(['kind' => 'status', 'value', 'label'])

<span
    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
    :class="$store.ui.badgeClass('{{ $kind }}', {{ $value }})"
    x-text="{{ $label }}"
    {{ $attributes }}
></span>
