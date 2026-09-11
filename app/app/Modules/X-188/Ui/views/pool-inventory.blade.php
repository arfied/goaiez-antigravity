<div>
    <x-surface.sample-state module="assigns a GO AI EZ number at signup" screen="pool_inventory" />
    <div class="pool-inventory p-4">
        <h3 class="text-lg font-bold">Number Pool Inventory</h3>
        @if($numbersByAreaCode->isEmpty())
            <p class="text-gray-500">Pool empty.</p>
        @else
            @foreach($numbersByAreaCode as $areaCode => $numbers)
                <h4 class="font-semibold mt-2">Area Code: {{ $areaCode }}</h4>
                <ul>
                    @foreach($numbers as $n)
                        <li>{{ $n->phone_number }} ({{ $n->status }})</li>
                    @endforeach
                </ul>
            @endforeach
        @endif
    </div>
</div>
