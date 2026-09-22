<div>
    <div class="forecast-tiles-view p-4">
        <h2 class="text-xl font-bold text-ink">Booked and collected, month by month</h2>

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <form wire:submit="submit" class="flex flex-col gap-2">
                @if($success) <div class="text-ink font-bold">{{ $success }}</div> @endif
                @if($error) <div class="text-ink font-bold">{{ $error }}</div> @endif
                <input type="text" wire:model="periodMonth" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Period month (e.g. 2026-09)">
                <input type="text" wire:model="riskScore" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Risk score (0-100)">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <form wire:submit="recordMonth" class="flex flex-col gap-2">
                @if($amountsSuccess) <div class="text-ink font-bold">{{ $amountsSuccess }}</div> @endif
                @if($amountsError) <div class="text-ink font-bold">{{ $amountsError }}</div> @endif
                <input type="text" wire:model="amountsMonth" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Period month (e.g. 2026-09)">
                <input type="text" wire:model="bookedCents" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Booked (cents)">
                <input type="text" wire:model="collectedCents" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Collected (cents)">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Record amounts</button>
            </form>
        </div>

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
