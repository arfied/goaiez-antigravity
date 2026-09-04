<x-surface.sample-state module="the internal half of the review split: a low rating becomes a `SupportTicket` with an SLA, routed to a human, tracked to a fix." screen="qa_queue_sladueat" />
<div>
    <div class="sla-queue-container p-4">
        <h3 class="text-lg font-bold">QA Queue & SLA Due Watch</h3>
        @if($tickets->isEmpty())
            <p class="text-gray-500">No pending SLA tickets.</p>
        @else
            <ul>
                @foreach($tickets as $t)
                    <li>#{{ $t->id }}: {{ $t->subject }} (Due: {{ $t->sla_due_at }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
