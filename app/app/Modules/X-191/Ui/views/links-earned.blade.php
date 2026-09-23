<div>
    <div class="links-earned-view p-4">
        <h2 class="text-lg font-bold text-ink">Earned links</h2>
        
        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <h3 class="font-semibold text-ink">Record Placement</h3>
            @if($success)
                <div class="text-ink-2 bg-paper p-2 border rounded">{{ $success }}</div>
            @endif
            @if($error)
                <div class="text-ink-2 bg-paper p-2 border rounded">{{ $error }}</div>
            @endif
            <form wire:submit="recordPlacement" class="flex flex-col gap-2">
                <input type="text" wire:model="placedUrl" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Placed URL">
                <input type="text" wire:model="anchorText" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Anchor Text">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>

        @if($placements->isEmpty())
            <x-ui.empty-state heading="No earned links yet.">A link you win through outreach is recorded here with the page that carries it.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($placements as $p)
                    <li class="py-2">
                        <span class="font-mono text-sm">{{ $p->placed_url }}</span>
                        <span class="text-ink-2">{{ $p->anchor_text }}</span>
                        <span class="text-sm text-ink-2">{{ $p->is_active ? 'live' : 'lost' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
