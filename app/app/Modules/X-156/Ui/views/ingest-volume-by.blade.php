<div>
    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-ink">Data coming in</h2>
            <x-ui.button size="default" variant="secondary" wire:click="toggleSample">
                {{ $isSample ? 'Exit sample' : 'Show a sample' }}
            </x-ui.button>
        </div>

        @if($actionFailed)
            <x-ui.error-panel heading="Action failed" />
        @endif

        @if($sources->isEmpty() && !$isSample)
            <x-ui.empty-state heading="No ingest runs yet" />
        @else
            <div class="mb-4">
                <p class="text-sm font-medium text-ink">{{ $tenantTotalRecords }} records across {{ $tenantTotalRuns }} runs</p>
            </div>

            <div class="space-y-4" wire:loading.class="opacity-50">
                @foreach($sources as $source)
                    <div class="border rounded-md p-4 bg-card shadow-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <div class="font-bold">{{ $source->source_name }}</div>
                            <div class="text-sm text-ink-2">{{ $sourceTypeLabels[$source->source_type] ?? $source->source_type }}</div>
                            <div class="text-sm mt-1">
                                {{ $source->runs_count }} runs, {{ $source->records_total }} records total
                            </div>
                            <div class="text-sm mt-1 text-ink-2">
                                Last run: {{ $source->last_run_at ? $source->last_run_at->diffForHumans() : 'never' }}
                            </div>
                            @if($source->rejections_count > 0)
                                <div class="text-sm text-red-500 mt-1">
                                    {{ $source->rejections_count }} rejections
                                </div>
                            @endif
                            <div class="mt-2">
                                @if($source->is_active)
                                    <x-ui.status-pill state="ok" label="Active" />
                                @else
                                    <x-ui.status-pill state="attention" label="Paused" />
                                @endif
                            </div>
                        </div>

                        <div class="flex gap-2">
                            @if($source->is_active)
                                <x-ui.button size="default" variant="secondary" wire:click="pause({{ $source->id }})">Pause</x-ui.button>
                            @else
                                <x-ui.button size="default" variant="secondary" wire:click="resume({{ $source->id }})">Resume</x-ui.button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        
        <div class="mt-8">
            <slot name="assistant"></slot>
        </div>
    </div>
</div>
