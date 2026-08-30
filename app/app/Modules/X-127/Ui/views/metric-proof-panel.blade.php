<div>
    <div class="metric-proof-view p-4">
        <h3 class="text-lg font-bold">Published Metric Proof Panel</h3>
        @if($metrics->isEmpty())
            <p class="text-gray-500">No public metrics published.</p>
        @else
            <ul>
                @foreach($metrics as $m)
                    <li>{{ $m->metric_key }}: {{ $m->published_value ?? '[PULLED]' }} [{{ $m->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
