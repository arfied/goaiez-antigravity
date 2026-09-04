<div>
    <x-surface.sample-state module="recurring service plans" screen="plans" />
    <div class="plans-view p-4">
        <h3 class="text-lg font-bold">Membership Plans</h3>
        @if($plans->isEmpty())
            <p class="text-gray-500">No membership plans created.</p>
        @else
            <ul>
                @foreach($plans as $p)
                    <li>#{{ $p->id }}: {{ $p->name }} (${{ number_format($p->price_cents / 100, 2) }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
