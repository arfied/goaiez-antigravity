<div>
    <div class="scorecard-view p-4">
        <h2 class="text-lg font-bold text-ink">Scorecards</h2>
        @if($scorecards->isEmpty())
            <p class="text-ink-2">No scorecard records.</p>
        @else
            <ul>
                @foreach($scorecards as $s)
                    <li>Staff {{ $s->staff_id }} ({{ $s->period_key }}): ${{ number_format($s->commissions_earned_cents / 100, 2) }} earned</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
