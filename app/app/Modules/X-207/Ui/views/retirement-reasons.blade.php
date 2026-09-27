<div>
    <div class="retirement-reasons-view p-4">
        <h2 class="text-lg font-bold text-ink">Retired devices</h2>
        @if($tokens->isEmpty())
            <p class="text-ink-2">No retired devices yet.</p>
        @else
            <ul>
                @foreach($tokens as $token)
                    <li>{{ $token->platform }} — {{ $token->retirement_reason }}</li>
                @endforeach
            </ul>
        @endif

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border">
            <h3 class="text-ink font-bold">Retire Device</h3>
            <form wire:submit="submit" class="flex flex-col gap-2">
                <input type="text" wire:model="deviceToken" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Device Token">
                <input type="text" wire:model="reason" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Reason">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
            <x-ui.toast kind="success" :message="$success" />
            <x-ui.toast kind="error" :message="$error" />
        </div>
    </div>
</div>
