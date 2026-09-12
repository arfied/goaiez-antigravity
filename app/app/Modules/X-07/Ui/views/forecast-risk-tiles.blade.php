<div>
    <div class="forecast-tiles-view p-4">
        <h2 class="text-xl font-bold text-ink">Booked and collected, month by month</h2>
        @if($forecasts->isEmpty())
            <x-ui.empty-state icon="○" heading="No forecast yet">
                When a forecast is worked out for a month, it appears here.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($forecasts as $f)
                    <li class="text-ink">{{ $f->period_month }} · booked ${{ number_format($f->booked_cents / 100, 2) }} · collected ${{ number_format($f->collected_cents / 100, 2) }} · {{ $f->churn_risk_pct }}% risk that customers leave</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
