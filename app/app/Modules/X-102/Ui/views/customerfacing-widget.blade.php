<div>
    <div class="chat-widget-container p-4">
        <h2 class="text-lg font-bold text-ink">Chat widget</h2>
        <p class="text-ink-2">Your published site carries the chat widget; every conversation it opens is a session here.</p>
        <p class="text-ink-2">{{ $total }} sessions · {{ $active }} active · {{ $escalated }} escalated</p>
        @if($error) <p class="mb-4 text-red-600">{{ $error }}</p> @endif
        @if($success) <p class="mb-4 text-green-600">{{ $success }}</p> @endif
        @if($sessions->isEmpty())
            <p class="text-ink-2">No chat sessions yet.</p>
        @else
            <ul>
                @foreach($sessions as $s)
                    <li>
                        <span class="font-mono text-sm">{{ $s->session_token }}</span> 
                        <span class="text-ink-2">[{{ $s->status }}] {{ $s->created_at?->format('Y-m-d H:i') }}</span>
                        <button wire:click="escalate({{ $s->id }})" class="rounded bg-brand text-white px-3 py-1 text-sm">Hand to a person</button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
