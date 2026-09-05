<div>
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Cooling List</h2>
        <x-ui.button wire:click="toggleSample" size="sm" variant="secondary">
            {{ $isSample ? 'Hide sample' : 'Show sample' }}
        </x-ui.button>
    </div>

    <x-ui.attention-card heading="A signal informs, it never sends">
        This view shows signals only. No messages are sent automatically.
    </x-ui.attention-card>

    @if($actionFailed)
        <x-ui.error-panel heading="Action failed" class="mt-4">
            {{ $actionFailed }}
        </x-ui.error-panel>
    @endif

    <div class="mt-6">
        @if($scores->isEmpty())
            <x-ui.empty-state heading="No cooling signals yet" />
        @else
            <x-ui.row-list>
                @foreach($scores as $score)
                    <x-ui.row>
                        <div class="flex justify-between items-center w-full">
                            <div>
                                <div class="font-medium">{{ $score->prospect_identifier }}</div>
                                <div class="text-sm text-gray-500">Score: {{ $score->signal_value }} | Status: {{ $score->cooling_status }}</div>
                            </div>
                            @if($score->cooling_status !== 'decayed')
                                <x-ui.button wire:click="markDecayed('{{ $score->prospect_identifier }}')" size="sm" variant="secondary">
                                    Mark Decayed
                                </x-ui.button>
                            @else
                                <span class="text-sm text-gray-500">Decayed</span>
                            @endif
                        </div>
                    </x-ui.row>
                @endforeach
            </x-ui.row-list>
        @endif
    </div>
</div>
