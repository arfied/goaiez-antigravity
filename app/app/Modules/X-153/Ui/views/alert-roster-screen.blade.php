<div>
    <div class="alert-roster-container p-4">
        <h2 class="text-lg font-bold text-ink">Team alerts</h2>
        @if($alerts->isEmpty())
            <p class="text-ink-2">No alerts recorded.</p>
        @else
            <ul>
                @foreach($alerts as $a)
                    <li>{{ $a->title }} [{{ $a->status }}]</li>
                @endforeach
            </ul>
        @endif

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <x-ui.toast kind="success" :message="$success" />
            <x-ui.toast kind="error" :message="$error" />
            <form wire:submit="broadcastAlert" class="flex flex-col gap-2">
                <input type="text" wire:model="title" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Title">
                <input type="text" wire:model="body" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Body">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Record an alert</button>
            </form>
        </div>
    </div>
</div>
