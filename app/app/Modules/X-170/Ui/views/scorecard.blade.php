<div>
    <x-surface.sample-state module="commission injection" screen="scorecard" />
    <div class="scorecard-view p-4">
        <h3 class="text-lg font-bold">Technician & Sales Scorecard</h3>
        @if($scorecards->isEmpty())
            <p class="text-gray-500">No scorecard records.</p>
        @else
            <ul>
                @foreach($scorecards as $s)
                    <li>Staff {{ $s->staff_id }} ({{ $s->period_key }}): ${{ number_format($s->commissions_earned_cents / 100, 2) }} earned</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
