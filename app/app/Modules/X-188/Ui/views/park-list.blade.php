<div>
    <x-surface.sample-state module="assigns a GO AI EZ number at signup" screen="park_list" />
    <div class="park-list p-4">
        <h3 class="text-lg font-bold">Parked Numbers</h3>
        @if($parks->isEmpty())
            <p class="text-gray-500">No numbers in 14-day parking.</p>
        @else
            <ul>
                @foreach($parks as $p)
                    <li>Parked until: {{ $p->park_until }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
