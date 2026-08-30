<div>
    <div class="action-log-container p-4">
        <h3 class="text-lg font-bold">Action Catalog & Dispatcher Log</h3>
        @if($invocations->isEmpty())
            <p class="text-gray-500">No action invocations recorded.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($invocations as $inv)
                    <li class="py-2">
                        <span class="font-mono text-sm">{{ $inv->action_name }}</span>
                        <span class="text-xs text-gray-500">{{ $inv->created_at }} ({{ $inv->geo_city }}, {{ $inv->geo_country }})</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
