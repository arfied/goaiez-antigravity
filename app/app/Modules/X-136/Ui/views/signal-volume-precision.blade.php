<div>
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Signal Volume & Precision</h2>
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
                    <div class="flex flex-col w-full">
                        <div class="flex justify-between items-center w-full">
                            <div>
                                <div class="font-medium text-lg capitalize">{{ str_replace('_', ' ', $stat->signal_type) }}</div>
                                <div class="text-sm text-gray-500 mt-1">
                                    Volume: {{ $stat->total_count }} total | {{ $stat->high_intent_count }} high-intent
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-medium text-blue-600">{{ $stat->precision_pct }}% Precision</div>
                                <div class="text-xs text-gray-400 mt-1">Decay: {{ $stat->decay_model }}</div>
                            </div>
                        </div>
                        
                        @if(!$isSample)
                        <div class="mt-4 flex gap-2 items-center">
                            <input type="number" wire:model="editHalfLife.{{ $stat->signal_type }}" placeholder="Half life (days)" class="border rounded p-1 text-sm bg-surface text-ink w-32">
                            <input type="number" step="0.001" wire:model="editDecayRate.{{ $stat->signal_type }}" placeholder="Rate (e.g. 0.05)" class="border rounded p-1 text-sm bg-surface text-ink w-32">
                            <button wire:click="saveDecay('{{ $stat->signal_type }}')" class="bg-surface text-ink border rounded p-1 text-sm">Save Decay</button>
                        </div>
                        @if(isset($refusals[$stat->signal_type]))
                            <div class="text-red-500 text-sm mt-1 font-bold">{{ $refusals[$stat->signal_type] }}</div>
                        @endif
                        @endif
                    </div>
                </x-ui.row>
            @endforeach
        </x-ui.row-list>
    @endif
</div>
