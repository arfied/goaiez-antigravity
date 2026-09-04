<div>
    <div class="gbp-card-container p-4 max-w-lg mx-auto md:max-w-4xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Google Business Profile</h3>
            <div class="flex items-center space-x-2">
                <button wire:click="toggleSample" class="text-sm px-2 py-1 bg-gray-200 rounded">
                    {{ $isSample ? 'Exit Sample' : 'Sample' }}
                </button>
            </div>
        </div>

        @if($isSample)
            <div class="mb-4 p-2 bg-yellow-100 text-yellow-800 rounded text-sm font-bold">
                SAMPLE DATA
            </div>
        @endif

        @if($isEmpty)
            <div class="text-gray-500 py-8 text-center border rounded">
                <p>connect Google</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($connections as $c)
                    <div class="border rounded p-4 flex flex-col md:flex-row justify-between items-start md:items-center {{ $c->profile_status === 'suspended' ? 'bg-red-50' : 'bg-green-50' }}">
                        <div class="space-y-2">
                            <div class="font-bold">{{ $c->external_label ?? 'Location' }}</div>
                            <div class="text-sm font-semibold {{ $c->profile_status === 'suspended' ? 'text-red-600' : 'text-green-600' }}">
                                Status: {{ $c->plain_status ?? $c->profile_status }}
                            </div>
                            
                            @if($c->latest_post)
                                <div class="text-xs text-gray-600 border-t pt-2 mt-2">
                                    <span class="font-bold">Latest Post:</span> {{ $c->latest_post->content }}
                                </div>
                            @endif
                        </div>
                        
                        <div class="mt-4 md:mt-0 flex flex-col space-y-2 items-end">
                            <button wire:click="toggleLog({{ $c->id }})" class="bg-gray-200 text-gray-800 px-3 py-1 rounded text-sm font-semibold">
                                {{ $viewingLogId === $c->id ? 'Hide Log' : 'View State Log' }}
                            </button>
                            <button wire:click="pollState({{ $c->id }})" class="bg-blue-600 text-white px-3 py-1 rounded text-sm font-semibold">
                                Poll Status
                            </button>
                        </div>
                    </div>
                    
                    @if($viewingLogId === $c->id && $c->latest_log)
                        <div class="bg-gray-100 p-4 rounded text-sm mt-2 font-mono">
                            <div class="font-bold mb-2">Latest Log Event: {{ $c->latest_log->event_type }}</div>
                            <div>{{ json_encode($c->latest_log->details) }}</div>
                            <div class="text-gray-500 mt-1">{{ $c->latest_log->created_at }}</div>
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
