<div>
    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-ink">Rows we could not take</h2>
            <x-ui.button size="default" variant="secondary" wire:click="toggleSample">
                {{ $isSample ? 'Exit sample' : 'Show a sample' }}
            </x-ui.button>
        </div>

        @if($actionFailed)
            <x-ui.error-panel heading="Action failed" />
        @endif

        @if($rejections->isEmpty() && !$isSample)
            <x-ui.empty-state heading="No rejected rows" />
        @else
            <div class="space-y-4" wire:loading.class="opacity-50">
                @foreach($rejections as $row)
                    <div class="border rounded-md p-4 bg-card shadow-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div class="flex-1">
                            <div class="font-bold whitespace-pre-wrap break-words text-red-600">{{ $row->rejection_reason }}</div>
                            <div class="text-sm font-semibold text-ink mt-1">
                                {{ $row->source_name }}
                                @if($row->source_id && !$row->is_active)
                                    <span class="text-xs text-ink-2">(Paused)</span>
                                @endif
                            </div>
                            <div class="text-sm mt-1 text-ink-2">
                                {{ $row->created_at->diffForHumans() }}
                                @if($row->record_count !== null)
                                    &middot; {{ $row->record_count }} records refused
                                @endif
                            </div>
                            <div class="mt-2">
                                @if($row->signature_verified)
                                    <x-ui.status-pill state="ok" label="Signature verified" />
                                @else
                                    <x-ui.status-pill state="attention" label="Unverified" />
                                @endif
                            </div>
                        </div>

                        @if($row->source_id)
                            <div class="flex gap-2 shrink-0">
                                @if($row->is_active)
                                    <x-ui.button size="default" variant="secondary" wire:click="pause({{ $row->source_id }})">Pause source</x-ui.button>
                                @else
                                    <x-ui.button size="default" variant="secondary" wire:click="resume({{ $row->source_id }})">Resume source</x-ui.button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
        
        <div class="mt-8">
            <slot name="assistant"></slot>
        </div>
    </div>
</div>
