<div>
    <div class="ttfm-dist p-4">
        <h2 class="text-lg font-bold text-ink">Time to first minute</h2>
        @if($runs->isEmpty())
            <x-ui.empty-state heading="No timings yet.">Each signup records how long it took to put a live agent on a real number.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($runs as $r)
                    <li class="py-2" wire:key="ttfm-{{ $r->id }}">
                        <span class="font-semibold">{{ $r->business_name }}</span>
                        <span class="text-sm text-ink-2 tabular-nums">{{ $r->ttfm_ms }} ms</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
