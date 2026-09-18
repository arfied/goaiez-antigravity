<div>
    <div class="latency-metrics p-4">
        <h2 class="text-lg font-bold text-ink">Call latency</h2>
        @if($calls->isEmpty())
            <x-ui.empty-state heading="No calls to time yet.">Once the assistant answers a call, how quickly it replied is recorded here.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($calls as $c)
                    <li class="py-2" wire:key="lat-{{ $c->id }}">
                        <span class="font-semibold">{{ $c->from_phone }}</span>
                        <span class="text-sm text-ink-2 tabular-nums">{{ $c->latency_ms }}ms</span>
                        <span class="text-sm text-ink-2">{{ $c->status }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
