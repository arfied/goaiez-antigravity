<div>
    <div class="rejection-rate-view p-4">
        <h3 class="text-lg font-bold">Rejected Conversions: {{ $rejectedCount }} ({{ number_format($rate, 1) }}% Rate)</h3>
        @if($reasons->isNotEmpty())
            <ul>
                @foreach($reasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
