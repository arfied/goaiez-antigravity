<div>
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Service Area Polygons</h2>
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
        <x-ui.error-panel heading="Action failed">
            {{ $actionFailed }}
        </x-ui.error-panel>
    @endif

    <div class="mb-6 p-4 border rounded-md">
        <h3 class="text-lg font-medium mb-3">Define New Polygon</h3>
        <form wire:submit="define" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Points (lat,lng per line)</label>
                <textarea wire:model="pointsText" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"></textarea>
                @error('pointsText') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <x-ui.submit target="define" busy="Saving...">
                Define
            </x-ui.submit>
        </form>
    </div>

    @if($polygons->isEmpty())
        <x-ui.empty-state heading="No polygons defined yet" />
    @else
        <div class="space-y-4">
            @foreach($polygons as $polygon)
                <div class="p-4 border rounded-md flex justify-between items-center">
                    <div>
                        <div class="font-medium">
                            {{ $polygon->polygon_name }}
                            @if($polygon->is_active)
                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                            @else
                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Inactive</span>
                            @endif
                        </div>
                        <div class="text-sm text-gray-500">
                            {{ count($polygon->coordinates) }} points
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            Spans {{ max(array_column($polygon->coordinates, 0)) - min(array_column($polygon->coordinates, 0)) }}° lat, {{ max(array_column($polygon->coordinates, 1)) - min(array_column($polygon->coordinates, 1)) }}° lng
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
