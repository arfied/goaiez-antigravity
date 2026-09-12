<div>
    <div class="p-4 space-y-6">
        <h2 class="text-lg font-bold text-ink">People a campaign stopped or paused for</h2>

        @if ($lastStoppedPersonId !== null)
            <x-ui.attention-card state="ok">
                @if ($lastStoppedCount === 0)
                    Nothing else was running
                @else
                    Stopped {{ $lastStoppedCount }} remaining {{ $lastStoppedCount === 1 ? 'sequence' : 'sequences' }}
                @endif
            </x-ui.attention-card>
        @endif

        <div wire:loading.delay wire:target="stopRemaining">
            <x-ui.skeleton label="Loading logs…" :lines="3" />
        </div>

        <div wire:loading.delay.remove wire:target="stopRemaining">
            @if ($failed)
                <x-ui.error-panel heading="We couldn't load the log" retry="$refresh">
                    There was an error communicating with the database.
                </x-ui.error-panel>
            @elseif ($runs->isEmpty())
                <x-ui.empty-state icon="○" heading="No campaign has stopped or paused yet">
                    When someone replies, or a campaign is paused for them, they appear here.
                </x-ui.empty-state>
            @else
                <x-ui.row-list>
                    @foreach($runs as $run)
                        @php $person = $people->get($run->person_id); $suppression = preg_replace('/\s*\([A-Z]-\d+\)$/', '', (string) $run->suppression_reason); @endphp
                        <x-ui.row>
                            <div class="flex items-center justify-between w-full">
                                <div>
                                    <div class="font-medium text-ink">
                                        {{ $person ? ($person->first_name . ' ' . $person->last_name) : 'Unknown Person' }}
                                    </div>
                                    <div class="text-sm text-ink-2">
                                        Campaign: {{ $run->campaign_id }} (Step {{ $run->current_step }})
                                    </div>
                                    <div class="text-sm text-ink-2">
                                        @if($run->is_suppressed)
                                            <x-ui.status-pill state="attention" label="{{ $suppression }}" />
                                        @elseif($run->stopped_reason)
                                            <x-ui.status-pill state="attention" label="{{ $run->stopped_reason }}" />
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <x-ui.button wire:click="stopRemaining({{ $run->person_id }})" size="default">
                                        Stop their remaining sequences
                                    </x-ui.button>
                                </div>
                            </div>
                        </x-ui.row>
                    @endforeach
                </x-ui.row-list>
            @endif
            </div>
    </div>
</div>
