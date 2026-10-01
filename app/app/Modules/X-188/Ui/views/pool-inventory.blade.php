<div>
    <div class="pool-inventory p-4">
        <h2 class="text-lg font-bold text-ink">Number pool</h2>

        @if($error)
            <p class="mb-4 text-red-600">{{ $error }}</p>
        @endif
        @if($success)
            <p class="mb-4 text-green-600">{{ $success }}</p>
        @endif

        <div class="mb-4">
            <button wire:click="claimNumber" class="rounded bg-ink text-paper px-4 py-2">
                Claim a number from the pool
            </button>
        </div>

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
