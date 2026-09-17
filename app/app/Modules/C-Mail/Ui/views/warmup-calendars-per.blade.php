<div>
    <div class="warmup-view p-4">
        <h2 class="text-lg font-bold text-ink">Domain warm-up</h2>
        @if($calendars->isEmpty())
            <x-ui.empty-state heading="No domains warming up.">A new sending domain warms up here, a few more messages each day.</x-ui.empty-state>
        @else
            <ul>
                @foreach($calendars as $c)
                    <li>Domain #{{ $c->mail_domain_id }}: Day {{ $c->current_day }} ({{ $c->sent_today }}/{{ $c->daily_allowance }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
