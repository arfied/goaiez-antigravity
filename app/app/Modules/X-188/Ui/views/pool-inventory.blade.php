<div>
    <div class="pool-inventory p-4">
        <h3 class="text-lg font-bold">Number Pool Inventory</h3>
        @if($numbers->isEmpty())
            <p class="text-gray-500">Pool empty.</p>
        @else
            <ul>
                @foreach($numbers as $n)
                    <li>{{ $n->phone_number }} ({{ $n->status }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
