<div wire:init="load">
    <x-surface.sample-state module="persistent chat on every page. **OPERATE** *("text everyone who called last week")*" screen="preview_card" />
    <div class="preview-card-view p-4" data-irreversible="{{ ($preview['is_irreversible'] ?? false) ? 'yes' : 'no' }}" data-action-key="{{ $actionKey }}">
        <h3 class="text-lg font-bold">Action Preview Card</h3>
        @if ($preview)
            <p>{{ $preview['projected_changes'] }}</p>
            @if ($preview['is_irreversible'])
                <p>Warning: this action cannot be undone and needs confirmation.</p>
            @endif
        @endif
    </div>
</div>
