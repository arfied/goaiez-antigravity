<div>
    <div class="upload-drop-view p-4">
        <h2 class="text-lg font-bold text-ink">Your documents</h2>
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
