<div>
    <div class="approval-queue p-4">
        <h2 class="text-lg font-bold text-ink">Approvals</h2>
        @if($items->isEmpty())
            <x-ui.empty-state heading="Nothing is waiting on you.">When the system wants a decision it cannot make alone, it lands here with what it proposes to do.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($items as $i)
                    <li class="py-2" wire:key="item-{{ $i->id }}">
                        <span class="font-semibold">{{ $i->subject }}</span>
                        <span class="text-sm text-ink-2">{{ $i->item_type }}</span>
                        <span class="text-sm text-ink-2">{{ $i->status }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
