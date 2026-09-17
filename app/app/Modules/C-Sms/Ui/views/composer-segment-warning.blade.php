<div>
    <div>
        <h2 class="text-lg font-bold text-ink">Message segments</h2>
        @if($texts->isEmpty())
            <x-ui.empty-state heading="No texts yet.">Each text you send is listed here with how many segments it billed as; anything over one segment is worth shortening.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($texts as $t)
                    <li class="py-2">
                        <span class="font-mono text-sm">{{ $t->recipient_phone }}</span>
                        <span class="text-ink-2">{{ $t->body }}</span>
                        <span class="text-sm text-ink-2 tabular-nums">{{ $t->segments_count }} {{ $t->segments_count === 1 ? 'segment' : 'segments' }} · {{ $t->encoding }}</span>
                        @if($t->segments_count > 1)<span class="text-sm text-accent">bills as {{ $t->segments_count }} segments</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
