<div>
    <div class="slot-board-view p-4">
        <h2 class="text-lg font-bold text-ink">Referral slots</h2>
        
        @if($success !== '')
            <p>{{ $success }}</p>
        @endif

        <form wire:submit.prevent="openSlot">
            <input wire:model="category" placeholder="Trade you want referrals from, e.g. roofing">
            @error('category') <span>{{ $message }}</span> @enderror
            <input wire:model="territoryZip" placeholder="ZIP">
            @error('territoryZip') <span>{{ $message }}</span> @enderror
            <button type="submit">Open slot</button>
        </form>

        @if($referralSlots->isEmpty())
            <p class="text-ink-2">No referral slots yet.</p>
        @else
            <ul>
                @foreach($referralSlots as $slot)
                    <li>
                        {{ $slot->category }} {{ $slot->territory_zip }} {{ $slot->status }}
                        @if($slot->status === 'open')
                            <input wire:model="partnerName.{{ $slot->id }}" placeholder="Company to invite">
                            <button type="button" wire:click="proposePartner({{ $slot->id }})">Propose partner</button>
                            @error('partnerName.'.$slot->id) <span>{{ $message }}</span> @enderror

                            <div>
                                <button type="button" wire:click="findPartners({{ $slot->id }})">Find partners</button>
                                @if(isset($found[$slot->id]))
                                    @if(empty($found[$slot->id]))
                                        <p>No listed business matches this trade and ZIP yet.</p>
                                    @else
                                        <ul>
                                            @foreach($found[$slot->id] as $listing)
                                                <li>
                                                    {{ $listing['company_name'] }}
                                                    <button type="button" wire:click="proposeFound({{ $slot->id }}, '{{ $listing['company_name'] }}')">Propose</button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
