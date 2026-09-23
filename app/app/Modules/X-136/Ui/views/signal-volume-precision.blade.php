<div>
    <div class="flex justify-end mb-4">
        <x-ui.button wire:click="toggleSample" size="sm" variant="secondary">
            {{ $isSample ? 'Hide sample' : 'Show sample' }}
        </x-ui.button>
    </div>

    @if($stats->isEmpty())
        <x-ui.empty-state heading="No signal stats yet" />
    @else
        <x-ui.row-list>
            @foreach($stats as $stat)
                <x-ui.row>
                    <div class="flex justify-between items-center w-full">
                        <div>
                            <div class="font-medium text-lg capitalize">{{ str_replace('_', ' ', $stat->signal_type) }}</div>
                            <div class="text-sm text-ink-2 mt-1">
                                Volume: {{ $stat->total_count }} total | {{ $stat->high_intent_count }} high-intent
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-medium text-blue-600">{{ $stat->precision_pct }}% Precision</div>
                            <div class="text-xs text-ink-2 mt-1">Decay: {{ $stat->decay_model }}</div>
                        </div>
                    </div>
                </x-ui.row>
            @endforeach
        </x-ui.row-list>
    @endif
</div>
