<div>
    <div class="audit-export p-4">
        <h2 class="text-lg font-bold text-ink">Approval history</h2>
        @if($decided->isEmpty())
            <x-ui.empty-state heading="No decisions yet.">Every approval or refusal is kept here with who made it and what they said.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($decided as $d)
                    <li class="py-2" wire:key="decided-{{ $d->id }}">
                        <span class="font-semibold">{{ $d->subject }}</span>
                        <span class="text-sm text-ink-2">{{ $d->status }}</span>
                        <span class="text-sm text-ink-2">{{ $d->decision_comment }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
