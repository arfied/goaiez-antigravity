<div>
    <div class="experiment-board-view p-4">
        <h2 class="text-lg font-bold">Content packs</h2>

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border">
            <h3 class="font-bold text-ink">Promote Pack</h3>
            @if($success)
                <div class="text-green-600 mb-2">{{ $success }}</div>
            @endif
            @if($error)
                <div class="text-red-600 mb-2">{{ $error }}</div>
            @endif
            <form wire:submit.prevent="promote" class="flex flex-col gap-2">
                <input type="text" wire:model="packName" placeholder="Pack Name" class="border rounded p-2 text-ink flex-1 bg-surface">
                <input type="text" wire:model="labelText" placeholder="Label Text" class="border rounded p-2 text-ink flex-1 bg-surface">
                <input type="number" wire:model="fleetSampleSize" placeholder="Fleet Sample Size" class="border rounded p-2 text-ink flex-1 bg-surface">
                <input type="text" wire:model="industry" placeholder="Industry" class="border rounded p-2 text-ink flex-1 bg-surface">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>

        @forelse ($packs as $pack)
            <div>
                {{ $pack->pack_name }} {{ $pack->label_text }}
                @if ($pack->is_promoted)
                    proven on {{ $pack->fleet_sample_size }} businesses
                @else
                    still testing
                @endif
            </div>
        @empty
            No content pack has been tested for you yet
        @endforelse
    </div>
</div>
