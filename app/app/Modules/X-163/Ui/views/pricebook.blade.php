<div>
    <div class="pricebook-container p-4">
        <h3 class="text-lg font-bold">Business Pricebook</h3>
        @if($items->isEmpty())
            <p class="text-gray-500">Pricebook empty.</p>
        @else
            <ul>
                @foreach($items as $i)
                    <li>{{ $i->service_name }}: ${{ number_format($i->price_cents / 100, 2) }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
