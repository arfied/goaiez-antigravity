<div>
    <div class="offer-composer-view p-4">

        @if($offers->isEmpty())
            <x-ui.empty-state heading="No offers">No offers have been made yet.</x-ui.empty-state>
        @else
            <ul class="text-ink mb-6 mt-4">
                @foreach($offers as $offer)
                    <li class="border rounded p-2 mb-2 bg-surface">
                        {{ $offer->prospect->partner_name ?? 'Unknown' }} - {{ $offer->offered_rate_bps }} bps (ceiling: {{ $offer->ceiling_rate_bps }} bps) - {{ $offer->terms_summary }} - {{ $offer->is_accepted ? 'accepted' : 'open' }}
                        @if(! $offer->is_accepted)
                            <button type="button" wire:click="acceptOffer({{ $offer->id }})" class="bg-surface text-ink border rounded px-2 py-1 ml-2">Accept</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border">
            <h2 class="font-bold text-ink">Make an offer</h2>
            @if($success)
                <div class="bg-surface text-ink border rounded p-4 mb-4">{{ $success }}</div>
            @endif
            @if($error)
                <div class="bg-surface text-ink border rounded p-4 mb-4">{{ $error }}</div>
            @endif
            <form wire:submit="makeOffer" class="flex flex-col gap-2">
                <input type="text" wire:model="prospectId" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Prospect ID">
                <input type="text" wire:model="offeredRateBps" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Offered Rate (bps)">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>
    </div>
</div>
