<div>
    <div class="p-4 space-y-6">
        <h3 class="text-lg font-bold text-ink">Do-Not-Text / Stopped Log</h3>

        @if ($isSample)
            <x-ui.sample />
        @endif

        @if ($lastStoppedPersonId !== null)
            <div class="bg-green-50 border border-green-200 text-green-800 rounded p-3">
                Stopped {{ $lastStoppedCount }} remaining sequence(s) for the selected person.
            </div>
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
                                            <span class="text-orange-600">Suppressed: {{ $run->suppression_reason }}</span>
                                        @elseif($run->stopped_reason)
                                            <span class="text-red-600">Stopped: {{ $run->stopped_reason }}</span>
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
