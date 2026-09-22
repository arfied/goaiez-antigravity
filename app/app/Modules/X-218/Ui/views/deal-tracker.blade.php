<div>
    <div class="deal-tracker-view p-4">
        <h2 class="text-lg font-bold">Influencer Sponsorship Deal Tracker</h2>
        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border border-surface">
            <h3 class="text-lg font-bold text-ink">Create Deal</h3>
            @if($success)
                <div class="p-2 bg-surface text-ink border rounded">{{ $success }}</div>
            @endif
            @if($error)
                <div class="p-2 bg-surface text-ink border rounded">{{ $error }}</div>
            @endif
            @if($influencers->isEmpty())
                <p class="text-ink">No influencers found. Add them in DiscoveryBoard first.</p>
            @else
                <form wire:submit="createDeal" class="flex gap-2">
                    <select wire:model="influencerId" class="border rounded p-2 text-ink flex-1 bg-surface">
                        <option value="">Select influencer...</option>
                        @foreach($influencers as $influencer)
                            <option value="{{ $influencer->id }}">{{ $influencer->handle }}</option>
                        @endforeach
                    </select>
                    <input type="number" wire:model="dealAmountCents" placeholder="Amount (cents)" class="border rounded p-2 text-ink flex-1 bg-surface">
                    <button type="submit" class="bg-surface text-ink border rounded p-2">Create</button>
                </form>
            @endif
        </div>

        @if($deals->isEmpty())
            <x-ui.empty-state heading="No deals yet">Create your first deal below to track your influencer sponsorships.</x-ui.empty-state>
        @else
            <ul class="mt-4 space-y-2">
                @foreach($deals as $deal)
                    <li class="text-ink p-2 border rounded">
                        {{ $deal->influencer->handle ?? 'unknown' }} - Amount: {{ $deal->deal_amount_cents }} cents - Status: {{ $deal->status }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
