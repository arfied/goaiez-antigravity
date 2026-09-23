<div>
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Cooling List ({{ $coolingTotal }})</h2>
        <x-ui.button wire:click="toggleSample" size="sm" variant="secondary">
            {{ $isSample ? 'Hide sample' : 'Show sample' }}
        </x-ui.button>
    </div>

    <x-ui.attention-card heading="A signal informs, it never sends">
        This view shows signals only. No messages are sent automatically.
    </x-ui.attention-card>

    @if($error) <div class="text-ink font-bold mt-4">{{ $error }}</div> @endif
    @if($success) <div class="text-ink font-bold mt-4">{{ $success }}</div> @endif

    <form wire:submit="recordSignal" class="mt-4 flex flex-col gap-2 bg-surface p-4 rounded border">
        <input type="text" wire:model="prospectIdentifier" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Prospect (e.g. acme-roofing)">
        <input type="text" wire:model="signalType" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Signal type (e.g. pricing_visit)">
        <input type="text" wire:model="signalScore" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Score (75 or more is high intent)">
        <button type="submit" class="bg-surface text-ink border rounded p-2">Record signal</button>
    </form>

    @if($actionFailed)
        <x-ui.error-panel heading="Action failed" class="mt-4">
            {{ $actionFailed }}
        </x-ui.error-panel>
    @endif

    <div class="mt-6">
        @if($scores->isEmpty())
            <x-ui.empty-state heading="Nobody is cooling" />
        @else
            <x-ui.row-list>
                @foreach($scores as $score)
                    <x-ui.row>
                        <div class="flex justify-between items-center w-full">
                            <div>
                                <div class="font-medium">{{ $score->prospect_identifier }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ str_replace('_', ' ', $score->signal_type) }} &middot; 
                                    Score: {{ $score->signal_value }} &middot; 
                                    {{ $score->days_quiet }} days quiet
                                </div>
                            </div>
                            <x-ui.button wire:click="markDecayed('{{ $score->prospect_identifier }}')" size="sm" variant="secondary">
                                Mark Decayed
                            </x-ui.button>
                        </div>
                    </x-ui.row>
                @endforeach
            </x-ui.row-list>
        @endif
    </div>
</div>
