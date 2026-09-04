<x-surface.sample-state module="ten fetch methods in cost order — **open data → free API → our own crawl → cheap paid → expensive paid** — **stopping at the first VALID SHAPE.** ⛔ **A provider that returns 200 with garbage is worse than one that returns 500** — a phone field containing "N/A" is a failure, not a value. Health checks" screen="provider_cost_per" />
<div>
    <div class="provider-cost-view p-4">
        <h3 class="text-lg font-bold">Enrichment Provider Cost-Per-Lookup</h3>
        @if($providers->isEmpty())
            <p class="text-gray-500">No active enrichment providers configured.</p>
        @else
            <ul>
                @foreach($providers as $p)
                    <li>Tier {{ $p->tier_level }}: {{ $p->provider_name }} (${{ number_format($p->cost_per_lookup_cents / 100, 2) }}/lookup)</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
