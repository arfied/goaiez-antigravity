<div class="flex flex-col gap-6 w-full max-w-3xl mx-auto p-4 md:p-6">
    <h2 class="text-lg font-bold text-ink">Calls</h2>
    
    <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
        <h3 class="font-semibold text-ink">Record Call</h3>
        @if($success)
            <div class="text-ink-2 bg-paper p-2 border rounded">{{ $success }}</div>
        @endif
        @if($error)
            <div class="text-ink-2 bg-paper p-2 border rounded">{{ $error }}</div>
        @endif
        <form wire:submit="recordCall" class="flex flex-col gap-2">
            <input type="text" wire:model="callSid" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Call SID">
            <input type="text" wire:model="fromPhone" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="From Phone">
            <input type="text" wire:model="toPhone" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="To Phone">
            <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
        </form>
    </div>

    <section>
        @if ($errorMessage)
            <x-ui.error-panel heading="Number Unavailable" retry="$refresh">
                {{ $errorMessage }}
            </x-ui.error-panel>
        @else
            <div class="flex flex-col gap-1 p-4 bg-card border border-rule rounded-[--radius-card]">
                <span class="text-sm font-medium text-ink-2">Assigned Number</span>
                @if ($assignedNumber)
                    <span class="font-mono text-lg text-ink">{{ $assignedNumber }}</span>
                @else
                    <span class="text-ink-3 italic">None</span>
                @endif
            </div>
        @endif
    </section>

    <section>
        <h3 class="text-xl font-bold text-ink mb-4">Call sessions</h3>

        <div wire:loading.delay>
            <x-ui.skeleton label="Loading call sessions" lines="3" />
        </div>

        <div wire:loading.remove.delay class="flex flex-col gap-4">
            @if ($calls->isEmpty())
                <x-ui.empty-state action="Refresh List" target="$refresh" heading="No calls yet">
                    When people call your assigned number, the history will appear here.
                </x-ui.empty-state>
            @else
                <ul class="flex flex-col gap-3">
                    @foreach ($calls as $call)
                        @php
                            $stateStr = 'unknown';
                            if (in_array($call->status, ['answered', 'completed'])) $stateStr = 'ok';
                            elseif ($call->status === 'missed') $stateStr = 'alert';
                            elseif ($call->status === 'ringing') $stateStr = 'attention';
                            
                            $hasVoicemail = isset($voicemailsExist[$call->id]);
                            $hasTranscript = isset($transcriptsExist[$call->id]);
                            $isSelected = $selectedSessionId === $call->id;
                        @endphp
                        
                        <li class="flex flex-col border border-rule rounded-[--radius-card] bg-card overflow-hidden transition-colors {{ $isSelected ? 'ring-2 ring-ink' : '' }}">
                            <button type="button" wire:click="select({{ $call->id }})" class="w-full text-left p-4 hover:bg-paper focus:outline-none flex flex-col gap-3">
                                <div class="flex justify-between items-start w-full gap-2">
                                    <span class="font-mono text-ink truncate">{{ $call->from_phone }}</span>
                                    <x-ui.status-pill state="{{ $stateStr }}" label="{{ ucfirst($call->status) }}" class="shrink-0" />
                                </div>
                                <div class="flex justify-between items-center w-full text-sm text-ink-2">
                                    <span>{{ $call->latency_ms }}ms</span>
                                    <div class="flex gap-2">
                                        @if($hasTranscript) <span class="bg-paper border border-rule px-2 py-0.5 rounded text-xs">Transcript</span> @endif
                                        @if($hasVoicemail) <span class="bg-paper border border-rule px-2 py-0.5 rounded text-xs">Voicemail</span> @endif
                                    </div>
                                </div>
                            </button>
                            
                            @if ($isSelected)
                                <div class="p-4 border-t border-rule bg-paper flex flex-col gap-6">
                                    @if ($turns && $turns->isNotEmpty())
                                        <div class="flex flex-col gap-2">
                                            <h4 class="text-xs font-semibold text-ink-3 uppercase tracking-wider">Transcript</h4>
                                            <div class="flex flex-col gap-3">
                                                @foreach($turns as $turn)
                                                    <div class="text-sm">
                                                        <span class="font-bold text-ink">{{ ucfirst($turn->speaker) }}:</span>
                                                        <span class="text-ink-2">{{ $turn->transcript }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                    
                                    @if ($voicemail)
                                        <div class="flex flex-col gap-2">
                                            <h4 class="text-xs font-semibold text-ink-3 uppercase tracking-wider">Voicemail</h4>
                                            @if ($voicemail->transcription)
                                                <p class="text-sm text-ink-2 italic">"{{ $voicemail->transcription }}"</p>
                                            @else
                                                <p class="text-sm text-ink-3 italic">Audio recording only.</p>
                                            @endif
                                        </div>
                                    @endif
                                    
                                    @if((!$turns || $turns->isEmpty()) && !$voicemail)
                                        <p class="text-sm text-ink-3 italic">No transcript or voicemail recorded.</p>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</div>
