<div>
    <div class="p-4 space-y-6">
        <div wire:loading.delay>
            <x-ui.skeleton label="Loading profile…" :lines="5" />
        </div>

        <div wire:loading.delay.remove>
            @if ($failed)
                <x-ui.error-panel heading="We couldn't load the profile" retry="$refresh">
                    There was an error communicating with the database.
                </x-ui.error-panel>
            @elseif (!$person)
                <x-ui.empty-state icon="○" heading="Person not found">
                    This profile doesn't exist or you don't have access.
                </x-ui.empty-state>
            @else
                <!-- Header -->
                <div class="flex flex-col gap-4 p-4 border border-rule rounded-[--radius-panel] bg-card">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-ink">{{ $person['first_name'] }} {{ $person['last_name'] }}</h2>
                            <p class="text-ink-2">{{ $person['email'] }} @if($person['email'] && $person['phone']) · @endif {{ $person['phone'] }}</p>
                        </div>
                        
                        <div class="flex flex-col items-end gap-2">
                            @if ($hasTakeover)
                                <x-ui.status-pill state="alert" label="Human takeover" />
                            @endif
                            
                            @if ($score)
                                <x-ui.status-pill :state="$score->grade === 'A' ? 'ok' : 'attention'" :label="'Score: ' . $score->lead_rating . ' (' . $score->grade . ')'">
                                    Score: {{ $score->lead_rating }} ({{ $score->grade }})
                                </x-ui.status-pill>
                            @endif
                        </div>
                    </div>
                    
                    @if (!empty($tags))
                        <div class="flex gap-2 flex-wrap">
                            @foreach ($tags as $tag)
                                <span class="rounded-full bg-paper border border-rule px-2 py-0.5 text-sm text-ink-2">{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Timeline -->
                <div>
                    <h3 class="text-lg font-semibold text-ink mb-3">Timeline</h3>
                    
                    @if ($messages->isEmpty())
                        <x-ui.empty-state icon="○" heading="No messages yet">
                            Conversations with this person will appear here.
                        </x-ui.empty-state>
                    @else
                        <div class="space-y-4 border-l-2 border-rule ml-3 pl-4">
                            @foreach ($messages as $message)
                                @php
                                    $convo = $conversations[$message->conversation_id] ?? null;
                                    $channel = $convo ? $convo->channel : 'unknown';
                                @endphp
                                <div class="bg-card border border-rule p-3 rounded-[--radius-panel]">
                                    <div class="flex justify-between items-center mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-2 bg-paper px-2 py-1 rounded">
                                                {{ $channel }}
                                            </span>
                                            <span class="text-sm font-medium {{ $message->direction === 'outbound' ? 'text-ok' : 'text-attention' }}">
                                                {{ ucfirst($message->direction->value) }}
                                            </span>
                                        </div>
                                        <span class="text-xs text-ink-3">{{ $message->created_at?->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-ink text-sm">{{ $message->body }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
