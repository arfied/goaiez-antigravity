<div>
    <div class="invoices-list p-4">
        <h3 class="text-lg font-bold">Customer Invoices</h3>
        @if($invoices->isEmpty())
            <p class="text-gray-500">No invoices issued.</p>
        @else
            <ul>
                @foreach($invoices as $inv)
                    <li>#{{ $inv->invoice_number }} - ${{ number_format($inv->total_cents / 100, 2) }} [{{ $inv->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
