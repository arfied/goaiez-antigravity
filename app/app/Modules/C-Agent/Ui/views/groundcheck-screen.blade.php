<div>
    <div class="groundcheck-container p-4">
        <h2 class="text-lg font-bold text-ink">Agent grounding</h2>
        <p class="text-ink-2">{{ $answered }} answered · {{ $refused }} refused · {{ $handoff }} handed off</p>
        
        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border border-surface">
            <h3 class="text-lg font-bold text-ink">Ask Agent</h3>
            @if($success)
                <div class="p-2 bg-surface text-ink border rounded">{{ $success }}</div>
            @endif
            @if($error)
                <div class="p-2 bg-surface text-ink border rounded">{{ $error }}</div>
            @endif
            <form wire:submit="askAgent" class="flex gap-2">
                <input type="text" wire:model="userMessage" placeholder="Ask something..." class="border rounded p-2 text-ink flex-1 bg-surface">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Ask</button>
            </form>
        </div>

        @if($turns->isEmpty())
            <p class="text-ink-2">No agent turns yet.</p>
        @else
            <ul>
                @foreach($turns as $turn)
                    <li>Turn {{ $turn->turn_number }}: {{ $turn->status }}@if($turn->refusal_code) ({{ $turn->refusal_code }})@endif — {{ \Illuminate\Support\Str::limit($turn->user_message, 80) }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
