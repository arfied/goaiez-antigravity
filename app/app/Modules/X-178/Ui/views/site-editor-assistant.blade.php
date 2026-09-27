<section class="site-editor-assistant-panel p-4">
    <h2 class="text-lg font-bold text-ink">Site editor</h2>
    @if($undoSuccess)
        <div class="text-ink bg-surface border rounded p-2 mb-4">{{ $undoSuccess }}</div>
    @endif
    @if($changes->isEmpty())
        <x-ui.empty-state heading="No design changes yet.">A change you ask for in plain words is recorded here with the block it touched and the contrast it kept.</x-ui.empty-state>
    @else
        <ul class="divide-y divide-rule">
            @foreach($changes as $c)
                <li class="py-2"><span class="font-semibold">{{ $c->change_type }}</span> <span class="text-ink-2">{{ $c->block_ref }}</span> <span class="text-sm text-ink-2 tabular-nums">contrast {{ $c->contrast_ratio }}:1</span> <span class="text-sm text-ink-2">{{ $c->status }}</span>
                    @if($c->status !== 'undone')
                        <button type="button" wire:click="undoChange({{ $c->id }})" class="bg-surface text-ink border rounded px-2 py-1 ml-2">Undo</button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <div class="mt-8 bg-surface p-4 border rounded">
        <h3 class="text-lg font-bold text-ink">Generate Lead-Capture Block</h3>
        <x-ui.toast kind="success" :message="$success" />
        <x-ui.toast kind="error" :message="$error" />
        <form wire:submit="generate" class="flex flex-col gap-4 mt-4">
            <input type="number" wire:model="pageId" placeholder="Page ID" class="border rounded p-2 text-ink bg-surface">
            <input type="text" wire:model="niche" placeholder="Niche" class="border rounded p-2 text-ink bg-surface">
            <button type="submit" class="bg-surface text-ink border rounded p-2">Generate Form</button>
        </form>
    </div>
</section>
