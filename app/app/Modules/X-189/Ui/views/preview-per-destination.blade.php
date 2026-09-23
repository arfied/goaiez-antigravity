<div>
    <div class="preview-dest-view p-4">
        <h2 class="text-lg font-bold text-ink">Branded media</h2>

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border border-rule">
            <h3 class="font-bold text-ink">Brand asset</h3>
            @if($success)
                <div class="bg-surface text-ink border rounded p-4 mb-4">{{ $success }}</div>
            @endif
            @if($error)
                <div class="bg-surface text-ink border rounded p-4 mb-4">{{ $error }}</div>
            @endif
            <form wire:submit="brandAsset" class="flex flex-col gap-2">
                <input type="text" wire:model="sourceAssetUrl" class="border rounded p-2 text-ink bg-surface" placeholder="Source Asset URL">
                <input type="text" wire:model="licenseSource" class="border rounded p-2 text-ink bg-surface" placeholder="License Source">
                <input type="text" wire:model="destination" class="border rounded p-2 text-ink bg-surface" placeholder="Destination (e.g. social)">
                <button type="submit" class="bg-surface text-ink border rounded p-2 font-bold">Brand</button>
            </form>
        </div>

        @if($media->isEmpty())
            <p class="text-ink-2">No branded media generated.</p>
        @else
            <ul>
                @foreach($media as $m)
                    <li>#{{ $m->id }}: [{{ $m->destination }}] {{ $m->source_asset_url }} -> {{ $m->output_media_url }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
