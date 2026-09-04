<x-surface.sample-state module="MRR prediction" screen="forecast_risk_tiles" />
<div>
    <div class="forecast-tiles-view p-4">
        <h3 class="text-lg font-bold">Revenue Forecast & Churn Risk Tiles</h3>
        @if($forecasts->isEmpty())
            <p class="text-gray-500">No monthly forecasts calculated.</p>
        @else
            <ul>
                @foreach($forecasts as $f)
                    <li>{{ $f->period_month }}: Booked ${{ number_format($f->booked_cents / 100, 2) }}, Collected ${{ number_format($f->collected_cents / 100, 2) }} (Risk: {{ $f->churn_risk_score }}%)</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
