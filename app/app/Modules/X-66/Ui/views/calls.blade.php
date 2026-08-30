<div>
    <div class="calls-container p-4">
        <h3 class="text-lg font-bold">Voice Call Sessions</h3>
        @if($calls->isEmpty())
            <p class="text-gray-500">No voice calls recorded.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($calls as $c)
                    <li class="py-2">
                        <span class="font-mono text-sm">{{ $c->from_phone }}</span>: {{ $c->status }} ({{ $c->latency_ms }}ms)
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
