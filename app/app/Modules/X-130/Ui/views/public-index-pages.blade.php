<div>
    <div class="public-index-pages-view p-4">
        <h2 class="text-lg font-bold text-ink">Demand in your area</h2>
        @if($regions->isEmpty())
            <x-ui.empty-state heading="No demand figures yet.">How busy your trade is in each area we cover shows up here once enough businesses are reporting.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($regions as $r)
                    <li class="py-2" wire:key="region-{{ $r->id }}">
                        <span class="font-semibold">{{ $r->region_name }}</span>
                        <span class="text-sm text-ink-2">{{ $r->trade_type }}</span>
                        <span class="text-sm text-ink-2 tabular-nums">{{ optional($r->series->where('is_published', true)->sortByDesc('period_date')->first())->demand_index ?? '—' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
