<div>
    <div class="roi-dashboard-view p-4">
        <h3 class="text-lg font-bold">Campaign ROI Dashboard</h3>
        @if($snapshots->isEmpty())
            <p class="text-gray-500">No ROI records.</p>
        @else
            <ul>
                @foreach($snapshots as $s)
                    <li>{{ $s->campaign_name }}: ${{ number_format($s->closed_revenue_cents / 100, 2) }} revenue / ${{ number_format($s->ad_spend_cents / 100, 2) }} spend</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
