<div>
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Service area</h2>
        <x-ui.button wire:click="toggleSample" size="sm" variant="secondary">
            {{ $isSample ? 'Hide sample' : 'Show sample' }}
        </x-ui.button>
    </div>

    @if($refusal)
        <x-ui.attention-card heading="Not a service area">
            {{ $refusal }}
        </x-ui.attention-card>
    @endif

    @if($actionFailed)
        <x-ui.error-panel heading="Action failed" />
    @endif

    <div class="mb-6 p-4 border rounded-md">
        <h3 class="text-lg font-medium mb-3">Define a service area</h3>
        <form wire:submit="define" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-ink">Name</label>
                <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-rule shadow-sm sm:text-sm">
                @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink">Points (lat,lng per line)</label>
                <textarea wire:model="pointsText" rows="4" class="mt-1 block w-full rounded-md border-rule shadow-sm sm:text-sm"></textarea>
                @error('pointsText') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <x-ui.submit target="define" busy="Saving...">
                Define
            </x-ui.submit>
        </form>
    </div>

    @if($polygons->isEmpty())
        <x-ui.empty-state heading="No service area yet" />
    @else
        <div class="space-y-4">
            @foreach($polygons as $polygon)
                <div class="p-4 border rounded-md flex justify-between items-center">
                    <div>
                        <div class="font-medium flex items-center">
                            {{ $polygon->polygon_name }}
                            @if($polygon->is_active)
                                <x-ui.status-pill state="ok" label="Active" class="ml-2" />
                            @else
                                <x-ui.status-pill state="attention" label="Inactive" class="ml-2" />
                            @endif
                        </div>
                        <div class="text-sm text-ink-2 mt-1">
                            {{ count($polygon->coordinates) }} points
                        </div>
                        <div class="text-xs text-ink-2 mt-1">
                            {{ $this->spans($polygon->coordinates) }}
                        </div>
                    </div>
                    <x-ui.button wire:click="toggle({{ $polygon->id }})" size="sm" variant="secondary">
                        {{ $polygon->is_active ? 'Deactivate' : 'Activate' }}
                    </x-ui.button>
                </div>
            @endforeach
        </div>
    @endif
</div>
