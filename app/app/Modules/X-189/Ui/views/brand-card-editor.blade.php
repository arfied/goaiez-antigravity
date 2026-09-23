<div>
    <x-surface.sample-state module="client photo → generated variant → branded card." screen="brand_card_editor" />
    <div class="card-editor-view p-4">
        <h2 class="text-lg font-bold">Brand Card Visual Editor</h2>
        
        <form wire:submit="save">
            <div>
                <label>Accent Color</label>
                <input type="text" wire:model="accentColor" placeholder="#0284c7">
                @error('accentColor') <span class="error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label>Badge Text</label>
                <input type="text" wire:model="badgeText">
                @error('badgeText') <span class="error">{{ $message }}</span> @enderror
            </div>
            <button type="submit">Save</button>
        </form>

        <div class="mt-4">
            <form wire:submit="uploadLogo">
                <input type="file" wire:model="logo">
                @error('logo') <span class="error">{{ $message }}</span> @enderror
                <button type="submit">Upload Logo</button>
            </form>
            @if($card?->logo_path)
                <button wire:click="removeLogo">Remove Logo</button>
            @endif
        </div>

        @if($lastMedia)
            <div class="mt-4">
                <h3>Last Media Output</h3>
                <img src="{{ URL::temporarySignedRoute('x-189.media', now()->addMinutes(15), ['business' => $lastMedia->business_id, 'branded' => $lastMedia->id]) }}" alt="Preview" />
            </div>
        @endif
    </div>
</div>
