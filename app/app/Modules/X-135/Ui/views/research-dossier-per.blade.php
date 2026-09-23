<div>
    <div class="flex justify-end mb-4">
        <x-ui.button wire:click="toggleSample" size="default" variant="secondary">
            {{ $isSample ? 'Hide sample' : 'Show sample' }}
        </x-ui.button>
    </div>

    <p class="text-sm text-ink-2 mb-4">Research calls this month: {{ $meter['count'] }} · ${{ $meter['dollars'] }}</p>

    <x-ui.attention-card state="ok" heading="Research fires only on distress" class="mb-4">
        A scored prospect is researched on its own and every call is metered; nothing on this screen starts a run.
    </x-ui.attention-card>

    @if($refusal)
        <x-ui.attention-card heading="Not grounded" class="mb-4">
            {{ $refusal }}
        </x-ui.attention-card>
    @endif

    @if($actionFailed)
        <x-ui.error-panel heading="Action failed" class="mb-4" />
    @endif

    @if($runs->isEmpty())
        <x-ui.empty-state heading="No dossier yet">A prospect is researched once it is scored; the dossier appears here on its own.</x-ui.empty-state>
    @else
        <x-ui.row-list class="mb-6">
            @foreach($runs as $run)
                <x-ui.row action="select({{ $run->id }})">
                    <span class="font-medium">Prospect {{ $run->prospect_id }}</span>
                    <span class="text-sm text-ink-2">{{ $run->signals_count }} signals · {{ $run->icebreakers_count }} icebreakers · {{ $run->created_at->toDateString() }}</span>
                    <x-ui.status-pill :state="$run->is_scored ? 'ok' : 'attention'" :label="$run->is_scored ? 'Scored' : 'Unscored'" />
                </x-ui.row>
            @endforeach
        </x-ui.row-list>
    @endif

    @if($selected)
        <div class="mb-6 p-4 border rounded-md">
            <h2 class="text-lg font-medium mb-3">Prospect {{ $selected->prospect_id }} — dossier</h2>

            @if(empty($selected->dossier))
                <p class="text-sm text-ink-2">No findings recorded.</p>
            @else
                @foreach($selected->dossier as $key => $value)
                    <p class="text-sm"><span class="font-medium">{{ str_replace('_', ' ', $key) }}:</span> {{ $value }}</p>
                @endforeach
            @endif

            <h3 class="font-medium mt-4 mb-2">Signals</h3>
            @if($selected->signals->isEmpty())
                <p class="text-sm text-ink-2">No signals recorded.</p>
            @else
                <x-ui.row-list>
                    @foreach($selected->signals as $signal)
                        <x-ui.row>
                            <span class="text-sm">{{ str_replace('_', ' ', $signal->signal_type) }} · {{ $signal->description }}</span>
                        </x-ui.row>
                    @endforeach
                </x-ui.row-list>
            @endif

            <h3 class="font-medium mt-4 mb-2">Icebreakers</h3>
            @if($selected->icebreakers->isEmpty())
                <p class="text-sm text-ink-2">No icebreaker yet — ground one below.</p>
            @else
                <x-ui.row-list>
                    @foreach($selected->icebreakers as $icebreaker)
                        <x-ui.row>
                            <div class="flex flex-col gap-1">
                                <span class="text-sm">{{ $icebreaker->opener_text }}</span>
                                <span class="text-xs text-ink-2">{{ $icebreaker->source_url }} · seen {{ $icebreaker->observed_date->toDateString() }}</span>
                            </div>
                        </x-ui.row>
                    @endforeach
                </x-ui.row-list>
            @endif

            <h3 class="font-medium mt-4 mb-2">Ground an icebreaker</h3>
            <form wire:submit="ground" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-ink-2">Opener — something TRUE about them</label>
                    <textarea wire:model="opener" rows="2" class="mt-1 block w-full rounded-md border-rule shadow-sm sm:text-sm"></textarea>
                    @error('opener') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-2">Source URL</label>
                    <input type="text" wire:model="sourceUrl" class="mt-1 block w-full rounded-md border-rule shadow-sm sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-2">Seen on (YYYY-MM-DD, today if blank)</label>
                    <input type="text" wire:model="observedDate" class="mt-1 block w-full rounded-md border-rule shadow-sm sm:text-sm">
                    @error('observedDate') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <x-ui.submit target="ground" busy="Grounding...">
                    Ground
                </x-ui.submit>
            </form>
        </div>
    @endif
</div>
