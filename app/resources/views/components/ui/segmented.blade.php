@props([
    'options' => [],
    'selected' => null,
    'action' => null,
])

<div class="inline-flex rounded-[--radius-control] border border-rule bg-card p-1">
    @foreach($options as $value => $label)
        @php
            $isSelected = (string)$selected === (string)$value;
        @endphp
        <button
            type="button"
            @if($action) wire:click="{{ $action }}({{ is_bool($value) ? ($value ? 'true' : 'false') : "'".$value."'" }})" @endif
            @class([
                'px-3 py-1 text-sm font-medium rounded transition-colors',
                'bg-paper text-ink shadow-sm' => $isSelected,
                'text-ink-2 hover:text-ink' => !$isSelected,
            ])
        >
            {{ $label }}
        </button>
    @endforeach
</div>
