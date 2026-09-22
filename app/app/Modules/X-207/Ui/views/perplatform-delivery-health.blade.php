<div>
    <h2 class="text-lg font-bold text-ink">Push health</h2>
    @if($platforms->isEmpty())
        <p class="text-ink-2">No devices registered.</p>
    @else
        <ul>
            @foreach($platforms as $p)
                <li>{{ $p->platform }}: {{ $p->devices }} devices</li>
            @endforeach
        </ul>
    @endif
    <ul>
        @foreach($statuses as $s)
            <li>{{ $s->status }}: {{ $s->n }}</li>
        @endforeach
    </ul>

    <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border">
        <h3 class="text-ink font-bold">Register Device</h3>
        <form wire:submit="submit" class="flex flex-col gap-2">
            <input type="text" wire:model="deviceToken" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Device Token">
            <select wire:model="platform" class="border rounded p-2 text-ink bg-surface">
                <option value="ios">ios</option>
                <option value="android">android</option>
                <option value="web">web</option>
            </select>
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
