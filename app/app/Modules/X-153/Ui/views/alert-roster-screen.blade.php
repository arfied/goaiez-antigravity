<div>
    <div class="alert-roster-container p-4">
        <h2 class="text-lg font-bold text-ink">Team alerts</h2>
        @if($alerts->isEmpty())
            <p class="text-ink-2">No alerts broadcasted.</p>
        @else
            <ul>
                @foreach($alerts as $a)
                    <li>{{ $a->title }} [{{ $a->status }}]</li>
                @endforeach
            </ul>
        @endif

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            @if($success)
                <div class="text-ink font-bold">{{ $success }}</div>
            @endif
            @if($error)
                <div class="text-ink-3 font-bold">{{ $error }}</div>
            @endif
            <form wire:submit="broadcastAlert" class="flex flex-col gap-2">
                <input type="text" wire:model="title" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Title">
                <input type="text" wire:model="body" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Body">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Broadcast</button>
            </form>
        </div>
    </div>
</div>
