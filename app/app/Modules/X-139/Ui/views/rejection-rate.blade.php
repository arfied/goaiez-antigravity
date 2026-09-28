<div>
    <div class="rejection-rate-view p-4">
        @if($rejectedCount > 0)
            <h2 class="text-lg font-bold">Rejected Conversions: {{ $rejectedCount }} ({{ number_format($rate, 1) }}% Rate)</h2>
        @else
            <x-ui.empty-state heading="No conversions have been uploaded.">Sending sales back to your ads waits on an ad-platform connection, which cannot be set up yet.</x-ui.empty-state>
        @endif
        @if($reasons->isNotEmpty())
            <ul>
                @foreach($reasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
