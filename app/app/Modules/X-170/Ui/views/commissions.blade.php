<div>
    <div class="commissions-view p-4">
        <h2 class="text-lg font-bold text-ink">Commissions</h2>
        @if($commissions->isEmpty())
            <p class="text-ink-2">No commission records.</p>
        @else
            <ul>
                @foreach($commissions as $c)
                    <li>#{{ $c->id }}: Staff {{ $c->staff_id }} - ${{ number_format($c->amount_cents / 100, 2) }} [{{ $c->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
