<div>
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Service Area Polygons</h2>
        <button wire:click="toggleSample" class="text-sm text-blue-600 underline">
            {{ $isSample ? 'Hide sample' : 'Show sample' }}
        </button>
    </div>

    @if($error)
        <x-ui.attention-card heading="Action failed">
            {{ $error }}
        </x-ui.attention-card>
    @endif

    <div class="mb-6 p-4 border rounded-md">
        <h3 class="text-lg font-medium mb-3">Define New Polygon</h3>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Points (lat,lng per line)</label>
                <textarea wire:model="pointsText" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"></textarea>
            </div>
            <button wire:click="define" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                Define
            </button>
        </div>
    </div>

    @if($polygons->isEmpty())
        <x-ui.empty-state heading="No polygons defined yet" />
    @else
        <div class="space-y-4">
            @foreach($polygons as $polygon)
                <div class="p-4 border rounded-md flex justify-between items-center">
                    <div>
                        <div class="font-medium">{{ $polygon->polygon_name }}</div>
                        <div class="text-sm text-gray-500">
                            {{ count($polygon->coordinates) }} points
                        </div>
                    </div>
                    <button wire:click="toggle({{ $polygon->id }})" class="text-sm px-3 py-1 border rounded-md {{ $polygon->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                        {{ $polygon->is_active ? 'Active' : 'Inactive' }}
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>
