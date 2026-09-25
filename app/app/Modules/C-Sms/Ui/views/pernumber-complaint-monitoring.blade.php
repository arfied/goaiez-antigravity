<div>
    <div>
        <h2 class="text-lg font-bold text-ink">Number health</h2>
        @if($rows->isEmpty())
            <x-ui.empty-state heading="No texts sent yet.">Every number you text is listed here with how many texts went out, how many were halted, and whether it asked you to stop.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($rows as $phone => $r)
                    <li class="py-2">
                        <span class="font-mono text-sm">{{ $phone }}</span>
                        <span class="text-ink-2 tabular-nums">{{ $r['sent'] }} sent · {{ $r['halted'] }} halted{{ $r['scheduled'] > 0 ? ' · ' . $r['scheduled'] . ' waiting for quiet hours to end' : '' }}</span>
                        @if(in_array($phone, $stopped, true))<span class="text-sm text-ink-2">stopped</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
