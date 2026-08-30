<div>
    <div class="failover-log-container p-4">
        <h3 class="text-lg font-bold">Carrier Delivery & Failover Receipts</h3>
        @if($receipts->isEmpty())
            <p class="text-gray-500">No carrier receipts recorded.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($receipts as $r)
                    <li class="py-2">
                        <span class="font-mono text-sm">{{ $r->carrier_name }}</span>
                        <span class="text-xs text-gray-500">#{{ $r->message_id }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
