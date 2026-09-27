<div>
    <div class="gbp-card-container p-4 max-w-lg mx-auto md:max-w-4xl">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-ink">Google profile</h2>
            <div class="flex items-center space-x-2">
                <x-ui.button wire:click="toggleSample" size="default" variant="secondary">
                    {{ $isSample ? 'Exit Sample' : 'Sample' }}
                </x-ui.button>
            </div>
        </div>

        @if($isSample)
            <div class="mb-4 p-2 bg-yellow-100 text-yellow-800 rounded text-sm font-bold">
                SAMPLE DATA
            </div>
        @endif

        @if($isEmpty)
            <x-ui.empty-state heading="Connect Google" icon="G">
                Link the Google Business Profile through Zernio and this card shows its status, latest post and any suspension in plain words.
            </x-ui.empty-state>
        @else
            <div class="space-y-4">
                @foreach($connections as $c)
                    <div class="border rounded p-4 flex flex-col md:flex-row justify-between items-start md:items-center {{ $c->profile_status === 'suspended' ? 'bg-red-50' : 'bg-green-50' }}">
                        <div class="space-y-2">
                            <div class="font-bold">{{ $c->external_label ?? 'Location' }}</div>
                            <div class="mt-1">
                                <x-ui.status-pill 
                                    state="{{ $c->profile_status === 'suspended' ? 'alert' : 'unknown' }}" 
                                    label="{{ $c->plain_status ?? $c->profile_status }}" 
                                />
                            </div>
                            
                            @if($c->latest_post)
                                <div class="text-xs text-ink-2 border-t pt-2 mt-2">
                                    <span class="font-bold">Latest Post:</span> {{ $c->latest_post->content }}
                                    <div class="mt-1">{{ $c->post_status_text }}</div>
                                </div>
                            @endif

                            @if(!$isSample)
                                <form wire:submit="postUpdate({{ $c->id }})" class="mt-4 border-t pt-4">
                                    <label class="block font-bold mb-1">Post an update to Google</label>
                                    <textarea wire:model="postContent.{{ $c->id }}" maxlength="1500" class="w-full border rounded p-2 mb-2"></textarea>
                                    <x-ui.submit size="default" target="postUpdate" busy="Posting…">Post to Google</x-ui.submit>
                                </form>
                            @endif
                        </div>
                        
                        <div class="mt-4 md:mt-0 flex flex-col space-y-2 items-end">
                            <x-ui.button wire:click="toggleLog({{ $c->id }})" size="default" variant="secondary">
                                {{ $viewingLogId === $c->id ? 'Hide Log' : 'View State Log' }}
                            </x-ui.button>
                        </div>
                    </div>
                    
                    @if($viewingLogId === $c->id && $c->latest_log)
                        <div class="bg-surface p-4 rounded text-sm mt-2 font-mono">
                            <div class="font-bold mb-2">Latest Log Event: {{ $c->latest_log->event_type }}</div>
                            <div>{{ json_encode($c->latest_log->details) }}</div>
                            <div class="text-ink-2 mt-1">{{ $c->latest_log->created_at }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
        
        <div class="mt-8">
            <slot name="assistant"></slot>
        </div>
    </div>
</div>
