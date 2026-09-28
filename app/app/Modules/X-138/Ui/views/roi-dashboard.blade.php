<div>
    <div class="roi-dashboard-view p-4">
        <h2 class="text-lg font-bold">Campaign ROI Dashboard</h2>
    @if ($results !== null)
        <div class="mt-2 mb-6">
            <p class="text-ink-2">What your website brought in over the last {{ $results['days'] }} days, counted from what actually happened.</p>
            <ul class="mt-2">
                <li>Visits to your site: {{ $results['visits'] ?? 'not measured yet — the page has not sent us a visit' }}</li>
                <li>Calls from your tracked number: {{ $results['calls'] }}</li>
                <li>Booking requests from the page: {{ $results['booking_requests'] }}</li>
                <li>Jobs booked: {{ $results['booked'] }}</li>
            </ul>
        </div>
    @endif
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
