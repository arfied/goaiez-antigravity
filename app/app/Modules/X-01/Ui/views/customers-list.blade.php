<div>
    <div class="p-4 space-y-4">
        <h2 class="text-lg font-bold text-ink">Customers</h2>
        
        @if ($isSample)
            <x-ui.sample />
        @endif

        <div wire:loading.delay wire:target="openPerson, readConversation">
            <x-ui.skeleton label="Loading customers…" :lines="3" />
        </div>

        <div wire:loading.delay.remove wire:target="openPerson, readConversation">
            @if ($failed)
                <x-ui.error-panel heading="We couldn't load the customers directory" retry="$refresh">
                    There was an error communicating with the database.
                </x-ui.error-panel>
            @elseif ($persons->isEmpty())
                <x-ui.empty-state icon="○" heading="No customers yet">
                    Your customers will appear here when they reach out.
                </x-ui.empty-state>
            @else
                <x-ui.row-list>
                    @foreach($persons as $p)
                        <x-ui.row>
                            <div class="flex items-center justify-between w-full">
                                <div>
                                    <div class="font-medium text-ink">{{ $p['first_name'] }} {{ $p['last_name'] }}</div>
                                    <div class="text-sm text-ink-2">
                                        {{ $p['email'] }}
                                        @if($p['email'] && $p['phone']) · @endif
                                        {{ $p['phone'] }}
                                    </div>
                                    @if(isset($leadScores[$p['id']]))
                                        <div class="mt-1">
                                            <x-ui.status-pill :state="$leadScores[$p['id']]->grade === 'A' ? 'ok' : 'attention'" :label="'Score: ' . $leadScores[$p['id']]->lead_rating . ' (' . $leadScores[$p['id']]->grade . ')'">
                                                Score: {{ $leadScores[$p['id']]->lead_rating }} ({{ $leadScores[$p['id']]->grade }})
                                            </x-ui.status-pill>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex gap-2">
                                    @if(isset($latestConversations[$p['id']]))
                                        <x-ui.button wire:click="readConversation({{ $latestConversations[$p['id']]->id }})" size="sm">
                                            Read Msg
                                        </x-ui.button>
                                    @endif
                                    <x-ui.button wire:click="openPerson({{ $p['id'] }})" size="sm">
                                        Open Profile
                                    </x-ui.button>
                                </div>
                            </div>
                        </x-ui.row>
                    @endforeach
                </x-ui.row-list>
                
                <div class="mt-4">
                    
                </div>
            @endif
        </div>
    </div>
</div>
