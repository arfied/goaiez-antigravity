<div>
    <div class="rejection-rate-view p-4">
        <h2 class="text-lg font-bold">Rejected Conversions: {{ $rejectedCount }} ({{ number_format($rate, 1) }}% Rate)</h2>
        @if($reasons->isNotEmpty())
            <ul>
                @foreach($reasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
