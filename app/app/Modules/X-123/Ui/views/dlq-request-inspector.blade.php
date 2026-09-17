<div>
    <div class="dlq-inspector p-4">
        <h2 class="text-lg font-bold text-ink">Failed deliveries</h2>
        @if($deadLetters->isEmpty())
            <x-ui.empty-state heading="No failed deliveries.">Every background subscriber is healthy. A delivery that fails ten times in a row lands here and you are emailed.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($deadLetters as $dlq)
                    <li class="py-2">
                        <span class="font-mono text-sm text-red-600">Event #{{ $dlq->event_log_id }}</span>
                        <span class="text-xs text-ink-2">{{ $dlq->error_message }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
