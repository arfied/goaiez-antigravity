<div>
    <div class="park-list p-4">
        <h2 class="text-lg font-bold text-ink">Parked numbers</h2>
        @if($parks->isEmpty())
            <p class="text-ink-2">No numbers in 14-day parking.</p>
        @else
            <ul>
                @foreach($parks as $p)
                    <li>Parked until: {{ $p->park_until }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
