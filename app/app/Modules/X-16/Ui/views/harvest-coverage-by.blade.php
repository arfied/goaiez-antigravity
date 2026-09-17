<div>
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Harvest coverage</h2>
        <button wire:click="toggleSample" class="text-sm text-blue-600 underline">
            {{ $isSample ? 'Hide sample' : 'Show sample' }}
        </button>
    </div>

    <x-ui.attention-card heading="Waiting on a Places key">
        Harvests run on the tenant's own free Places key (§474) and no key is connected yet; holding harvested records costs nothing.
    </x-ui.attention-card>

    @if(! $hasPlaces && ! $isSample)
        <div class="mt-6">
            <x-ui.empty-state heading="No places harvested yet" />
        </div>
    @else
        <div class="mt-6">
            <p class="mb-4 text-sm font-medium text-ink">
                {{ $placesTotal }} places harvested across {{ $territoriesTotal }} territories
            </p>
            <div class="space-y-4">
                @foreach($rows as $row)
                    <div class="p-4 border rounded-md flex justify-between items-center">
                        <div class="font-medium text-ink">{{ $row->polygon_name }}</div>
                        <div class="text-ink-2">{{ $row->places_inside }} places</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
