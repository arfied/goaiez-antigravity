<div>
    <div class="p-4 space-y-6">
        <h3 class="text-lg font-bold text-ink">Do-Not-Text / Stopped Log</h3>

        @if ($isSample)
            <x-ui.sample />
        @endif

        @if ($lastStoppedPersonId !== null)
            <x-ui.attention-card>
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
                <x-ui.empty-state icon="○" heading="No sequences stopped">
                    There are no stopped or suppressed campaign runs yet.
                </x-ui.empty-state>
            @else
                <x-ui.row-list>
                    @foreach($runs as $run)
                        @php $person = $people->get($run->person_id); @endphp
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
                                            <x-ui.status-pill state="attention" label="Suppressed: {{ $run->suppression_reason }}" />
                                        @elseif($run->stopped_reason)
                                            <x-ui.status-pill state="attention" label="Stopped: {{ $run->stopped_reason }}" />
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <x-ui.button wire:click="stopRemaining({{ $run->person_id }})" size="sm">
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
