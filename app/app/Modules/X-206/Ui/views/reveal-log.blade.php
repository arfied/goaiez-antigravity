<div>
    <div class="reveal-log-container p-4">
        <h2 class="text-lg font-bold text-ink">Reveal log</h2>
        @if($logs->isEmpty())
            <p class="text-ink-2">No credential reveal audit records.</p>
        @else
            <ul class="divide-y divide-rule">
                @foreach($logs as $log)
                    <li class="py-2">
                        <span class="font-mono text-sm">{{ $log->service_name }}</span>
                        <span class="text-xs {{ $log->status === 'permitted' ? 'text-green-600' : 'text-red-600' }}">[{{ $log->status }}]</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
