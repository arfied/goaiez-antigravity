<div>
    <div class="p-4 space-y-6">
        <h2 class="text-lg font-bold text-ink">People inside a campaign right now</h2>

        @if ($lastStoppedPersonId !== null)
            <x-ui.attention-card state="ok">
                @if ($lastStoppedCount === 0)
                    Nothing was running for them any more
                @else
                    Stopped {{ $lastStoppedCount }} {{ $lastStoppedCount === 1 ? 'campaign' : 'campaigns' }} — listed under Campaigns stopped or paused
                @endif
            </x-ui.attention-card>
        @endif

        @if ($runs->isEmpty())
            <x-ui.empty-state icon="○" heading="No campaign is running for anyone yet">
                When someone is enrolled in a campaign, they appear here. Nothing advances a campaign yet.
            </x-ui.empty-state>
        @else
            <x-ui.row-list>
                @foreach($runs as $run)
                    @php $person = $people->get($run->person_id); @endphp
                    <x-ui.row>
                        <div class="flex items-center justify-between w-full">
                            <div>
                                <div class="font-medium text-ink">
                                    {{ $person ? ($person['first_name'] . ' ' . $person['last_name']) : 'Unknown Person' }}
                                </div>
                                <div class="text-sm text-ink-2">
                                    Campaign: {{ $run->campaign_id }} (Step {{ $run->current_step }})
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <x-ui.status-pill state="ok" label="Enrolled at step 1" />
                                <x-ui.button wire:click="stop({{ $run->person_id }})" wire:loading.attr="disabled" wire:target="stop" size="default">
                                    Stop every campaign for this person
                                </x-ui.button>
                            </div>
                        </div>
                    </x-ui.row>
                @endforeach
            </x-ui.row-list>
        @endif
    </div>
</div>
