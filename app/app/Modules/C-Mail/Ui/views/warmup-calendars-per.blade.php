<div>
    <div class="warmup-view p-4">
        <h3 class="text-lg font-bold">Domain Warmup Calendars</h3>
        @if($calendars->isEmpty())
            <p class="text-gray-500">No domains warming up.</p>
        @else
            <ul>
                @foreach($calendars as $c)
                    <li>Domain #{{ $c->mail_domain_id }}: Day {{ $c->current_day }} ({{ $c->sent_today }}/{{ $c->daily_allowance }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
