<div>
    <x-surface.sample-state module="aggregate demand by trade and geography" screen="demand_tile" />
    <div class="demand-tile-view p-4">
        <h3 class="text-lg font-bold">Local Market Demand Index</h3>
        @if($latest)
            <p class="text-2xl font-bold tabular-nums">{{ $latest->demand_index }}</p>
            <p class="text-sm text-ink-2">Period {{ $latest->period_date }}</p>
        @else
            <x-ui.empty-state heading="No published demand figure yet.">A figure appears once a region has enough reporting sources.</x-ui.empty-state>
        @endif
    </div>
</div>
