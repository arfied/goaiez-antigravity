<div>
    <div class="pool-inventory p-4">
        <h2 class="text-lg font-bold text-ink">Number pool</h2>
        @if($numbersByAreaCode->isEmpty())
            <p class="text-ink-2">Pool empty.</p>
        @else
            @foreach($numbersByAreaCode as $areaCode => $numbers)
                <h3 class="font-semibold mt-2">Area Code: {{ $areaCode }}</h3>
                <ul>
                    @foreach($numbers as $n)
                        <li>{{ $n->phone_number }} ({{ $n->status }})</li>
                    @endforeach
                </ul>
            @endforeach
        @endif
    </div>
</div>
