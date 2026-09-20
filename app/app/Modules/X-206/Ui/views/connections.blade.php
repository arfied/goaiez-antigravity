<div>
    <div class="connections-container p-4">
        <h2 class="text-lg font-bold text-ink">Connections</h2>
        @if($credentials->isEmpty())
            <p class="text-ink-2">No credential connections configured.</p>
        @else
            <ul class="divide-y divide-rule">
                @foreach($credentials as $c)
                    <li class="py-2">
                        <span class="font-mono text-sm font-semibold">{{ $c->service_name }}</span>
                        <span class="text-xs text-ink-2">({{ $c->key_hint }})</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border">
            <h3 class="text-ink font-bold">Add Connection</h3>
            <form wire:submit="submit" class="flex flex-col gap-2">
                <input type="text" wire:model="serviceName" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Service Name">
                <input type="password" wire:model="secret" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Secret">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
            @if($success)
                <div class="text-ink bg-surface p-2 mt-2">{{ $success }}</div>
            @endif
            @if($error)
                <div class="text-ink-2 bg-surface p-2 mt-2">{{ $error }}</div>
            @endif
        </div>
    </div>
</div>
