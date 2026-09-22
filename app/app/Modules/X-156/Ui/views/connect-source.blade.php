<div>
    <div class="p-6 space-y-6">
        <div class="flex justify-end">
            <x-ui.button size="default" variant="secondary" wire:click="toggleSample">
                {{ $isSample ? 'Exit sample' : 'Show a sample' }}
            </x-ui.button>
        </div>

        @if($actionNotice && str_starts_with($actionNotice, 'Connected'))
            <div class="bg-green-50 text-green-800 p-4 rounded-md">
                {{ $actionNotice }}
            </div>
        @elseif($actionNotice)
            <x-ui.error-panel heading="Action failed">
                {{ $actionNotice }}
            </x-ui.error-panel>
        @endif

        <form wire:submit="connect" class="space-y-4 mb-6">
            <div>
                <select wire:model="sourceType" class="mt-1 block w-full rounded-md border-rule shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @foreach($sourceTypeLabels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('sourceType') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <input type="text" wire:model="sourceName" placeholder="Connection name..." class="mt-1 block w-full rounded-md border-rule shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('sourceName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-ui.submit target="connect" busy="Connecting..." size="default">Connect</x-ui.submit>
            </div>
        </form>

        <div wire:loading>
            <x-ui.skeleton label="Loading sources..." />
        </div>

        <div wire:loading.remove>
            @if(! $isSample && $sources->isEmpty())
                <x-ui.empty-state heading="No sources connected yet">
                    Use the form above to connect a new data source.
                </x-ui.empty-state>
            @else
                <div class="space-y-4">
                    @foreach($sources as $s)
                        <div class="bg-paper border border-rule rounded-lg p-4 shadow-sm">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="text-sm font-medium text-ink">
                                        {{ $s->source_name }}
                                    </div>
                                    <div class="text-xs text-ink-2 mt-1">
                                        {{ $sourceTypeLabels[$s->source_type] ?? $s->source_type }}
                                    </div>
                                    <div class="text-sm text-ink-2 mt-2">
                                        @if($s->last_run_time)
                                            {{ $s->last_run_records }} records, {{ \Carbon\Carbon::parse($s->last_run_time)->diffForHumans() }}
                                        @else
                                            never
                                        @endif
                                        &middot; {{ $s->rejections_count }} rejections
                                    </div>
                                </div>
                                <div>
                                    @if(! $s->is_active)
                                        <x-ui.status-pill state="attention" label="Paused" />
                                    @elseif($s->last_run_time)
                                        <x-ui.status-pill state="ok" label="Synced" />
                                    @else
                                        <x-ui.status-pill state="unknown" label="Waiting for first sync" />
                                    @endif
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end">
                                @if($s->is_active)
                                    <x-ui.button size="default" variant="secondary" wire:click="pause({{ $s->id }})">Pause</x-ui.button>
                                @else
                                    <x-ui.button size="default" variant="secondary" wire:click="resume({{ $s->id }})">Resume</x-ui.button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        
        <div class="mt-8">
            <slot name="assistant"></slot>
        </div>
    </div>
</div>
