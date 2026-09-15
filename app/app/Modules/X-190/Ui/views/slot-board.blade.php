<div>
    <div class="slot-board-view p-4">
        <h2 class="text-lg font-bold text-ink">Referral slots</h2>
        @if($referralSlots->isEmpty())
            <p class="text-ink-2">No referral slots yet.</p>
        @else
            <ul>
                @foreach($referralSlots as $slot)
                    <li>{{ $slot->category }} {{ $slot->territory_zip }} {{ $slot->status }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
