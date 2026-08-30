<div>
    <div class="credits-ledger p-4">
        <h3 class="text-lg font-bold">Credits Ledger</h3>
        @if($entries->isEmpty())
            <p class="text-gray-500">Ledger empty.</p>
        @else
            <ul>
                @foreach($entries as $e)
                    <li>#{{ $e->id }} ({{ $e->entry_type }}): ${{ number_format($e->amount_hundredths_cents / 10000, 2) }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
