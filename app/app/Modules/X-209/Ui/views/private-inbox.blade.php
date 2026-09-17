<div>
    <div class="private-inbox-view p-4">
        <h2 class="text-lg font-bold text-ink">Fixer inbox</h2>
        @if($commands->isEmpty())
            <x-ui.empty-state heading="No commands yet.">When someone on the crew texts the assistant, what they asked and what it did appears here.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($commands as $c)
                    <li class="py-2" wire:key="cmd-{{ $c->id }}">
                        <span class="font-semibold">{{ $c->raw_command }}</span>
                        <span class="text-sm text-ink-2">{{ $c->parsed_intent }}</span>
                        <span class="text-sm text-ink-2">{{ $c->status }}</span>
                        <span class="text-sm text-ink-2 tabular-nums">{{ $c->eta_minutes_delayed }} min</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
