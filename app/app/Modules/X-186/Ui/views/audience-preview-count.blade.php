<div>
    <div class="audience-preview-count-view p-4">
        <h2 class="text-lg font-bold">Target Audience Preview</h2>
        @if ($people === 0)
            <p>No one is in a campaign yet</p>
        @else
            <p>{{ $people }} people are in a running campaign</p>
            <p>{{ $count }} campaign runs are active</p>
        @endif

        <div class="mt-4 border-t pt-4">
            <h3 class="font-bold">Enrol Person</h3>
            
            @if($error)
                <div class="text-red-500 mb-2">{{ $error }}</div>
            @endif
            
            @if($success)
                <div class="text-green-500 mb-2">{{ $success }}</div>
            @endif

            <form wire:submit="enrol" class="space-y-4 max-w-sm mt-2">
                <div>
                    <label class="block text-sm">Campaign ID</label>
                    <input type="text" wire:model="campaignId" class="border p-2 w-full">
                </div>
                <div>
                    <label class="block text-sm">Person ID</label>
                    <input type="text" wire:model="personId" class="border p-2 w-full">
                </div>
                <button type="submit" class="bg-surface text-ink border rounded p-2">Enrol</button>
            </form>
        </div>
    </div>
</div>
