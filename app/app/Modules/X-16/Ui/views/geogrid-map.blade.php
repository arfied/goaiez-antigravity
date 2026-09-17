<div>
    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-ink">Geo-grid</h2>
            <x-ui.button size="default" variant="secondary" wire:click="toggleSample">
                {{ $isSample ? 'Exit sample' : 'Show a sample' }}
            </x-ui.button>
        </div>

        @if($actionFailed)
            <x-ui.error-panel heading="Action failed" />
        @endif

        <form wire:submit="generate" class="space-y-4 mb-6 border rounded-md p-4 bg-card shadow-sm">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-ink">Grid Name</label>
                    <input type="text" wire:model="gridName" class="mt-1 block w-full rounded-md border-rule shadow-sm sm:text-sm" placeholder="e.g. Downtown">
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink">Center Lat</label>
                    <input type="text" wire:model="centerLat" class="mt-1 block w-full rounded-md border-rule shadow-sm sm:text-sm" placeholder="-90..90">
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink">Center Lng</label>
                    <input type="text" wire:model="centerLng" class="mt-1 block w-full rounded-md border-rule shadow-sm sm:text-sm" placeholder="-180..180">
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink">Radius (km)</label>
                    <input type="number" wire:model="radiusKm" class="mt-1 block w-full rounded-md border-rule shadow-sm sm:text-sm">
                </div>
            </div>
            <div>
                <x-ui.submit target="generate" busy="Generating...">Generate Grid</x-ui.submit>
            </div>
        </form>

        @if($anyUnscanned)
            <x-ui.attention-card heading="Waiting on a Places key">
                Geo-grid scans run on the tenant's own free Places key (§474) and no key is connected yet; holding grids costs nothing.
            </x-ui.attention-card>
        @endif

        @if($grids->isEmpty() && !$isSample)
            <x-ui.empty-state heading="No grids yet" />
        @else
            <div class="space-y-6" wire:loading.class="opacity-50">
                @foreach($grids as $grid)
                    <div class="border rounded-md p-4 bg-card shadow-sm flex flex-col md:flex-row gap-6">
                        <div class="flex-1">
                            <div class="font-bold text-lg">{{ $grid->grid_name }}</div>
                            <div class="text-sm text-ink-2 mt-1">
                                Center: {{ number_format((float) $grid->center_lat, 4) }}, {{ number_format((float) $grid->center_lng, 4) }} &middot; {{ $grid->radius_km }} km
                            </div>
                            <div class="text-sm mt-1">
                                {{ $grid->points_scanned }} of {{ $grid->points_total }} points scanned
                            </div>
                            <div class="text-sm text-ink-2 mt-1">
                                Last scan: {{ $grid->last_scanned_at ? \Carbon\Carbon::parse($grid->last_scanned_at)->diffForHumans() : 'never' }}
                            </div>
                            <div class="mt-3">
                                @if($grid->points_scanned === 0)
                                    <x-ui.status-pill state="attention" label="Waiting on a Places key" />
                                @else
                                    <x-ui.status-pill state="ok" label="Scanned" />
                                @endif
                            </div>
                            <div class="mt-4">
                                <x-ui.button size="default" variant="secondary" wire:click="regenerate({{ $grid->id }})">Regenerate</x-ui.button>
                            </div>
                        </div>
                        
                        <div class="shrink-0 bg-surface p-2 rounded-md border">
                            <div class="grid grid-cols-5 gap-1 w-32 h-32">
                                @foreach($grid->grid_points as $point)
                                    <div class="bg-card border rounded flex items-center justify-center text-xs font-semibold {{ (is_array($point) && array_key_exists('rank', $point) && $point['rank'] !== null) ? 'text-green-700 bg-green-50' : 'text-ink-2' }}">
                                        {{ (is_array($point) && array_key_exists('rank', $point) && $point['rank'] !== null) ? $point['rank'] : '·' }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        
        <div class="mt-8">
            <slot name="assistant"></slot>
        </div>
    </div>
</div>
