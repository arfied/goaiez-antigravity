<div>
    <div class="onetap-approval-view p-4">
        <h2 class="text-lg font-bold text-ink">One-Tap Approval</h2>

        @if($toast)
            <div class="text-ink-2 bg-surface border p-2 mb-4 rounded">{{ $toast }}</div>
        @endif

        @if($commands->isEmpty())
            <x-ui.empty-state heading="Nothing waiting.">Commands from staff whose action is below level {{ $autoLevel }} land here first.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($commands as $c)
                    <li class="py-4 flex flex-col gap-2" wire:key="cmd-{{ $c->id }}">
                        <div>
                            <span class="font-semibold text-ink">{{ $c->raw_command }}</span>
                            <span class="text-sm text-ink-2 ml-2">{{ $c->parsed_intent }}</span>
                            <span class="text-sm text-ink-2 ml-2 tabular-nums">ETA {{ $c->eta_minutes_delayed }} min</span>
                            <span class="text-sm text-ink-2 ml-2">Staff #{{ $c->staff_person_id }}</span>
                        </div>
                        <div class="flex gap-2 mt-2">
                            <button type="button" wire:click="approve({{ $c->id }})" class="bg-surface text-ink border rounded p-2">Approve</button>
                            <button type="button" wire:click="delegate({{ $c->id }})" class="bg-surface text-ink border rounded p-2">Hand to owner</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
