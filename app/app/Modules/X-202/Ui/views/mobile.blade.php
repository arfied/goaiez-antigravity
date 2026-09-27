<div>
    <x-surface.sample-state module="⭐⭐⭐ **The one queue every `L1` decision in the platform lands in — from all 114 modules.** *A refund proposal, a domino collision, a renewal with an ambiguous clause, a migration run, an offer post, an agency impersonation request.* ⛔⛔ **THE LAW OF THIS MODULE: IT ROUTES AND NEVER DECIDES.** *The item's own `P-195` floor governs the outcome; `X-202` decides only WHO SEES IT and WHEN.* ⭐⭐⭐ **Every item is a RECOMMENDATION PAYLOAD — *"the human is a reviewer, not a researcher."*** *An item with no pre-calculated proposed action FAILS THE WRITE.*" screen="mobile" />

<x-ui.toast kind="success" :message="$success" />
<x-ui.toast kind="error" :message="$error" />

@if($items->isEmpty())
    <p class="text-ink-2">Nothing is waiting on you.</p>
@else
    <ul class="divide-y divide-rule">
        @foreach($items as $i)
            <li class="py-2" wire:key="m-{{ $i->id }}">
                <span class="font-semibold text-ink">{{ $i->subject }}</span>
                <span class="text-sm text-ink-2">{{ $i->status }}</span>
                <button type="button" wire:click="approveItem({{ $i->id }})" class="bg-surface text-ink border rounded px-2 py-1 ml-2">Approve</button>
                <button type="button" wire:click="rejectItem({{ $i->id }})" class="bg-surface text-ink border rounded px-2 py-1 ml-2">Reject</button>
            </li>
        @endforeach
    </ul>
@endif
</div>
