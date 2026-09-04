<div>
    <x-surface.sample-state module="typed events, any module subscribes; **dead-letter queue at 10 consecutive failures + the tenant emailed**; exponential backoff 1" screen="dlq_request_inspector" />
    <div class="dlq-inspector p-4">
        <h3 class="text-lg font-bold">Dead Letter Queue Inspector</h3>
        @if($deadLetters->isEmpty())
            <p class="text-gray-500">DLQ is empty. All background subscribers healthy.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($deadLetters as $dlq)
                    <li class="py-2">
                        <span class="font-mono text-sm text-red-600">Event #{{ $dlq->event_log_id }}</span>
                        <span class="text-xs text-gray-500">{{ $dlq->error_message }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
