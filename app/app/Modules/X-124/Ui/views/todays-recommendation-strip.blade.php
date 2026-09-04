<div>
    <div class="mb-6 sm:mb-8">
        <h2 class="text-sm font-semibold text-ink-2 uppercase tracking-wider mb-3">Needs you now</h2>
        @if($recs->isEmpty())
            <x-ui.empty-state icon="✓" heading="All clear">
                Nothing on your desk right now.
            </x-ui.empty-state>
        @else
            <x-ui.row-list>
                @foreach($recs as $r)
                    <x-ui.row>
                        <div class="flex-1 min-w-0 pr-4">
                            <p class="text-sm font-medium text-ink truncate">{{ $r->title }}</p>
                        </div>
                        <div class="flex gap-2">
                            <x-ui.button variant="quiet" size="default" wire:click="dismiss({{ $r->id }})">Dismiss</x-ui.button>
                            <x-ui.button variant="secondary" size="default" wire:click="preview({{ $r->id }})">Review</x-ui.button>
                        </div>
                    </x-ui.row>
                    @if($previewingId === $r->id)
                        <div class="p-4 bg-paper border-t border-rule text-sm">
                            <div class="mb-4">
                                <p class="text-ink-2">{{ collect($previewData)->get('projected_changes', 'Reviewing...') }}</p>
                            </div>
                            <div class="flex justify-end gap-3">
                                @if(collect($previewData)->get('is_irreversible', false) && !$previewIsConfirmed)
                                    <x-ui.button variant="primary" size="default" wire:click="execute({{ $r->id }})">Confirm</x-ui.button>
                                @else
                                    <x-ui.button variant="primary" size="default" wire:click="execute({{ $r->id }})">Execute</x-ui.button>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </x-ui.row-list>
        @endif
    </div>
</div>
