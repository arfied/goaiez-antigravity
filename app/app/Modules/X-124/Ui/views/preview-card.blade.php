<div wire:init="load">
    <div class="preview-card-view p-4" data-irreversible="{{ ($preview['is_irreversible'] ?? false) ? 'yes' : 'no' }}" data-action-key="{{ $actionKey }}">
        <h2>Preview</h2>
        @if ($preview)
            <p>{{ $preview['projected_changes'] }}</p>
            @if ($preview['is_irreversible'])
                <p>Warning: this action cannot be undone and needs confirmation.</p>
            @endif
        @endif
    </div>
</div>
