<div>
    <div class="approval-queue p-4">
        <h2 class="text-lg font-bold text-ink">Approvals</h2>

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border border-rule">
            <h3 class="font-bold text-ink">Enqueue item</h3>
            @if($success)
                <div class="bg-surface text-ink border rounded p-4 mb-4">{{ $success }}</div>
            @endif
            @if($error)
                <div class="bg-surface text-ink border rounded p-4 mb-4">{{ $error }}</div>
            @endif
            <form wire:submit="enqueueItem" class="flex flex-col gap-2">
                <input type="text" wire:model="itemType" class="border rounded p-2 text-ink bg-surface" placeholder="Item Type">
                <input type="text" wire:model="subject" class="border rounded p-2 text-ink bg-surface" placeholder="Subject">
                <input type="text" wire:model="note" class="border rounded p-2 text-ink bg-surface" placeholder="Note">
                <button type="submit" class="bg-surface text-ink border rounded p-2 font-bold">Enqueue</button>
            </form>
        </div>

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
