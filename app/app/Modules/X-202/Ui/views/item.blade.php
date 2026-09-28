<div>
    <x-surface.sample-state module="⭐⭐⭐ **The one queue every `L1` decision in the platform lands in — from all 114 modules.** *A refund proposal, a domino collision, a renewal with an ambiguous clause, a migration run, an offer post, an agency impersonation request.* ⛔⛔ **THE LAW OF THIS MODULE: IT ROUTES AND NEVER DECIDES.** *The item's own `P-195` floor governs the outcome; `X-202` decides only WHO SEES IT and WHEN.* ⭐⭐⭐ **Every item is a RECOMMENDATION PAYLOAD — *"the human is a reviewer, not a researcher."*** *An item with no pre-calculated proposed action FAILS THE WRITE.*" screen="item" />
    <div class="item-view p-4">
        <h2 class="text-lg font-bold">{{ $item->subject }}</h2>

        <x-ui.toast kind="success" :message="$success" />
        <x-ui.toast kind="error" :message="$error" />

        <div class="my-4 p-4 border rounded">
            <p><strong>Item Type:</strong> {{ $item->item_type }}</p>
            <p><strong>Status:</strong>
                {{ $item->status }}
                @if($item->status === 'escalated')
                    <span class="bg-red-200 text-red-800 text-xs px-2 py-1 rounded font-bold uppercase">escalated</span>
                @endif
            </p>
            <p><strong>Decided At:</strong> {{ $item->decided_at ? $item->decided_at->toDateTimeString() : 'Pending' }}</p>
            <p><strong>Decider ID:</strong> {{ $item->decided_by_user_id ?? 'None' }}</p>
            <p><strong>Chain:</strong> {{ $item->chain ? $item->chain->name : 'No Chain' }}</p>
            <p><strong>Payload:</strong></p>
            <pre class="bg-surface p-2 rounded text-xs mt-1">{{ json_encode($item->payload, JSON_PRETTY_PRINT) }}</pre>
        </div>

        @if($item->status === 'pending' || $item->status === 'escalated')
            <div class="mb-4 flex gap-2">
                <input type="text" wire:model="escalateReason" class="border rounded p-2 text-ink bg-surface w-full" placeholder="Reason to escalate">
                <input type="text" wire:model="decisionComment" class="border rounded p-2 text-ink bg-surface w-full" placeholder="Comment">
            </div>

            <div class="flex gap-2">
                @if($item->status === 'pending')
                    <button type="button" wire:click="escalateItem" class="bg-surface text-ink border rounded px-4 py-2 font-bold">Escalate</button>
                @endif
                <button type="button" wire:click="approveItem" class="bg-surface text-ink border rounded px-4 py-2 font-bold">Approve</button>
                <button type="button" wire:click="rejectItem" class="bg-surface text-ink border rounded px-4 py-2 font-bold">Reject</button>
            </div>
        @endif
    </div>
</div>
