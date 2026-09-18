<div>
    <div class="coverage-by-trade-view p-4">
        <h2 class="text-lg font-bold text-ink">Demand by trade</h2>
        @if($trades->isEmpty())
            <x-ui.empty-state heading="No trades covered yet.">The trades we have enough reporting for appear here.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($trades as $t)
                    <li class="py-2" wire:key="trade-{{ $loop->index }}">
                        <span class="font-semibold">{{ $t->trade_type }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
