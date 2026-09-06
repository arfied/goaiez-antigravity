<div>
    <x-surface.sample-state module="click- and keyword-level attribution" screen="roi_dashboard" />
    <div class="roi-dashboard-view p-4">
        <h3 class="text-lg font-bold">Campaign ROI Dashboard</h3>
        @if($snapshots->isEmpty())
            <x-ui.empty-state
                icon="🎯"
                heading="No campaign results yet"
                action="Install pixel"
                href="{{ route('account.pixel-install') }}"
            >We will show what each campaign earned once the tag is sending us clicks.</x-ui.empty-state>
        @else
            <ul>
                @foreach($snapshots as $s)
                    <li>{{ $s->campaign_name }}: ${{ number_format($s->closed_revenue_cents / 100, 2) }} revenue / ${{ number_format($s->ad_spend_cents / 100, 2) }} spend</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
