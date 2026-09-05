<div>
    <x-surface.sample-state module="⭐⭐⭐ **The one queue every `L1` decision in the platform lands in — from all 114 modules.** *A refund proposal, a domino collision, a renewal with an ambiguous clause, a migration run, an offer post, an agency impersonation request.* ⛔⛔ **THE LAW OF THIS MODULE: IT ROUTES AND NEVER DECIDES.** *The item's own `P-195` floor governs the outcome; `X-202` decides only WHO SEES IT and WHEN.* ⭐⭐⭐ **Every item is a RECOMMENDATION PAYLOAD — *"the human is a reviewer, not a researcher."*** *An item with no pre-calculated proposed action FAILS THE WRITE.*" screen="queue" />
    <div class="approval-queue p-4">
        <h3 class="text-lg font-bold">Pending Approval Queue</h3>
        @if($items->isEmpty())
            <p class="text-gray-500">No items pending approval.</p>
        @else
            <ul>
                @foreach($items as $i)
                    <li>#{{ $i->id }}: {{ $i->subject }} [{{ $i->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
