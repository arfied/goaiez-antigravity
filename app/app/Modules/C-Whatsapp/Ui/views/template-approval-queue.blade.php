<div>
    <div class="whatsapp-queue-view p-4">
        <h2 class="text-lg font-bold text-ink">Template approval queue</h2>
        @if($pending->isEmpty())
            <x-ui.empty-state heading="Nothing waiting for approval.">Templates you have submitted but that have not come back yet wait here.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($pending as $p)
                    <li class="py-2" wire:key="pending-{{ $p->id }}">
                        <span class="font-semibold">{{ $p->name }}</span>
                        <span class="text-sm text-ink-2">{{ $p->body_text }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
