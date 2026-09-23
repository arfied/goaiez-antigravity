<div>
    <div class="upload-drop-view p-4">
        <h2 class="text-lg font-bold text-ink">Your documents</h2>
        
        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <h3 class="font-semibold text-ink">Add Document</h3>
            @if($success)
                <div class="text-ink-2 bg-paper p-2 border rounded">{{ $success }}</div>
            @endif
            @if($error)
                <div class="text-ink-2 bg-paper p-2 border rounded">{{ $error }}</div>
            @endif
            <form wire:submit="createDocument" class="flex flex-col gap-2">
                <input type="text" wire:model="title" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Title">
                <textarea wire:model="content" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Content"></textarea>
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>

        @if($documents->isEmpty())
            <x-ui.empty-state heading="No documents yet.">A price list, a contract or a spec sheet you send us appears here once we have read it.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($documents as $d)
                    <li class="py-2" wire:key="doc-{{ $d->id }}">
                        <span class="font-semibold">{{ $d->title }}</span>
                        <span class="text-sm text-ink-2">{{ $d->status }}</span>
                        <span class="text-sm text-ink-2">{{ $d->mime_type }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
