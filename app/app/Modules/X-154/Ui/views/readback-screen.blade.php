<div>
    <div class="readback-screen-view p-4">
        
        <x-ui.toast kind="error" :message="$error" />

        <x-ui.toast kind="success" :message="$success" />

        <form wire:submit="setMapping" class="mb-6 flex flex-col gap-4 bg-surface p-4 border rounded mt-4">
            <div>
                <label class="text-ink-2">Generic Term</label>
                <input type="text" wire:model="genericTerm" class="border rounded p-2 text-ink w-full bg-surface">
            </div>
            <div>
                <label class="text-ink-2">Preferred Term</label>
                <input type="text" wire:model="preferredTerm" class="border rounded p-2 text-ink w-full bg-surface">
            </div>
            <div>
                <label class="text-ink-2">Category</label>
                <input type="text" wire:model="category" class="border rounded p-2 text-ink w-full bg-surface">
            </div>
            <button type="submit" class="bg-surface text-ink border rounded p-2 w-32">Save Mapping</button>
        </form>

        <form wire:submit="previewReadback" class="mb-6 flex flex-col gap-4 bg-surface p-4 border rounded mt-4">
            <div>
                <label class="text-ink-2">Template Text to Preview</label>
                <input type="text" wire:model="templateText" class="border rounded p-2 text-ink w-full bg-surface">
            </div>
            <button type="submit" class="bg-surface text-ink border rounded p-2 w-32">Preview</button>
        </form>

        @if ($preview !== null)
            <div class="bg-surface text-ink border rounded p-4 mb-6">
                <h2 class="text-md font-bold mb-2 text-ink-2">Preview Output:</h2>
                <p>{{ $preview }}</p>
            </div>
        @endif

        @if ($lexicons->isEmpty())
            <x-ui.empty-state heading="No vocabulary mappings recorded">
                No vocabulary mapping has been configured for this tenant yet.
            </x-ui.empty-state>
        @else
            <div class="flex flex-col gap-2">
                @foreach ($lexicons as $lexicon)
                    <div class="bg-surface text-ink border rounded p-4 flex flex-col">
                        <span><span class="text-ink-2">Generic:</span> {{ $lexicon->generic_term }}</span>
                        <span><span class="text-ink-2">Preferred:</span> {{ $lexicon->preferred_term }}</span>
                        <span><span class="text-ink-2">Category:</span> {{ $lexicon->category }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
