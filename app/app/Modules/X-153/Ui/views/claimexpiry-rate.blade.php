<div>
    <div class="claim-expiry-container p-4">
        <h2 class="text-lg font-bold text-ink">Claim expiry</h2>
        <p class="text-ink-2">Claims release after 30 minutes. {{ $active }} active · {{ $expired }} expired · {{ $rate }}% expiry rate.</p>
        @if($claims->isEmpty())
            <p class="text-ink-2">No claims yet.</p>
        @else
            <ul>
                @foreach($claims as $c)
                    <li>Alert #{{ $c->alert_id }} claimed by user #{{ $c->claimed_by_user_id }} — {{ $c->status }}@if($c->expires_at) (expires {{ $c->expires_at->format('Y-m-d H:i') }})@endif</li>
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
            <form wire:submit="claimAlert" class="flex flex-col gap-2">
                <input type="text" wire:model="code" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Code">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Claim</button>
            </form>
        </div>
    </div>
</div>
