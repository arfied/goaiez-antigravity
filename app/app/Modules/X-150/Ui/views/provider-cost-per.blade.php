<div>
    <div class="p-6 space-y-6">

        @if($actionNotice && str_starts_with($actionNotice, 'Provider'))
            <div class="bg-green-50 text-green-800 p-4 rounded-md">
                {{ $actionNotice }}
            </div>
        @elseif($actionNotice)
            <x-ui.error-panel heading="Action failed">
                {{ $actionNotice }}
            </x-ui.error-panel>
        @endif

        <div wire:loading>
            <x-ui.skeleton label="Loading provider data..." />
        </div>

        <div wire:loading.remove>
            @if(! $isSample && $roster->isEmpty())
                <x-ui.empty-state heading="No providers on the roster yet" action="Show a sample" target="toggleSample">
                    The roster fills on the first provider.fetch
                </x-ui.empty-state>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="bg-paper border border-rule rounded-lg p-4 shadow-sm" data-tile="spend" data-value="{{ $totalSpendCents }}">
                        <div class="font-medium text-ink">Total spend</div>
                        <div class="text-2xl font-semibold text-ink mt-2">
                            ${{ number_format($totalSpendCents / 100, 2) }}
                        </div>
                    </div>
                    <div class="bg-paper border border-rule rounded-lg p-4 shadow-sm" data-tile="valid" data-value="{{ $totalValid }}">
                        <div class="font-medium text-ink">Total valid</div>
                        <div class="text-2xl font-semibold text-ink mt-2">
                            {{ $totalValid }}
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    @foreach($roster as $p)
                        <div class="bg-paper border border-rule rounded-lg p-4 shadow-sm">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="text-sm font-medium text-ink">
                                        {{ $p->provider_name }} (Tier {{ $p->tier_level }})
                                    </div>
                                    <div class="text-xs text-ink-2 mt-1">
                                        {{ $p->attempts }} attempts &middot; {{ $p->valid }} valid &middot; {{ $p->junk }} junk &middot; {{ $p->cost_per_lookup_cents }}c/lookup
                                    </div>
                                    <div class="text-lg font-semibold text-ink mt-2">
                                        @if($p->cost_per_valid_cents !== null)
                                            ${{ number_format($p->cost_per_valid_cents / 100, 2) }}
                                        @else
                                            &mdash;
                                        @endif
                                        <span class="text-xs text-ink-2 font-normal">/ valid</span>
                                    </div>
                                </div>
                                <div>
                                    @if(! $p->is_active)
                                        <x-ui.status-pill state="attention" label="Cold" />
                                    @elseif($p->valid > 0)
                                        <x-ui.status-pill state="ok" label="In use" />
                                    @else
                                        <x-ui.status-pill state="unknown" label="No valid record yet" />
                                    @endif
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end">
                                @if($p->is_active)
                                    <x-ui.button size="default" variant="secondary" wire:click="markCold({{ $p->id }})">Mark as not in use</x-ui.button>
                                @else
                                    <x-ui.button size="default" variant="secondary" wire:click="warmUp({{ $p->id }})">Mark as in use</x-ui.button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        
        <div class="mt-8">
            <slot name="assistant"></slot>
        </div>
    </div>
</div>
