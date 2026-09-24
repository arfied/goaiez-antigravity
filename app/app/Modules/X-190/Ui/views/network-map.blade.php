<div>
    <div class="network-map-view p-4">
        <h2 class="text-lg font-bold text-ink">Partner network</h2>

        <div>
            <h3>Your listing</h3>
            
            @if($success)
                <div>{{ $success }}</div>
            @endif

            @if($listing && $listing->is_listed)
                <p>Listed as {{ $listing->company_name }} &middot; {{ $listing->category }} &middot; {{ $listing->territory_zip }}</p>
                <button type="button" wire:click="unlist">Hide my listing</button>
            @else
                <form wire:submit.prevent="listBusiness">
                    <div>
                        <input type="text" wire:model="companyName" placeholder="Business name">
                        @error('companyName') <span>{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <input type="text" wire:model="category" placeholder="Trade you take referrals for, e.g. roofing">
                        @error('category') <span>{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <input type="text" wire:model="territoryZip" placeholder="ZIP">
                        @error('territoryZip') <span>{{ $message }}</span> @enderror
                    </div>
                    <button type="submit">List my business</button>
                </form>
            @endif
        </div>

        @if($partners->isEmpty())
            <p class="text-ink-2">No partners in your network yet.</p>
        @else
            <ul>
                @foreach($partners as $partner)
                    <li>{{ $partner->company_name }} {{ $partner->category }} {{ $partner->territory_zip }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
