<div>
    <div class="mb-6 sm:mb-8">
        <h2 class="text-sm font-semibold text-ink-2 uppercase tracking-wider mb-3">Payment Declines & Exceptions</h2>
        
        <div wire:loading>
            <x-ui.skeleton label="Loading declines..." />
        </div>
        
        <div wire:loading.remove>
            @if($loadError)
                <x-ui.error-panel heading="Could not load declines">
                    {{ $loadError }}
                </x-ui.error-panel>
            @else
                <div class="mb-4">
                    <p class="text-3xl font-display font-bold text-ink">${{ number_format($totalDeclined / 100, 2) }}</p>
                    <div class="flex items-center gap-1">
                        <p class="text-xs text-ink-2">Declined</p>
                        <p class="text-xs text-ink-2">&middot;</p>
                        <p class="text-xs text-ink-2">{{ $charges->count() }} declined</p>
                    </div>
                </div>
                @if($charges->isEmpty())
                    <x-ui.empty-state icon="✅" heading="No declines">
                        No payments have been declined or reversed.
                    </x-ui.empty-state>
                @else
                    <x-ui.row-list>
                        @foreach($charges as $charge)
                            <x-ui.row>
                                <div class="flex-1 min-w-0 pr-4">
                                    <p class="text-sm font-medium text-ink truncate">Ref {{ $charge->reference_id }}</p>
                                </div>
                                <div class="text-sm font-semibold text-ink">
                                    ${{ number_format($charge->amount_cents / 100, 2) }}
                                </div>
                            </x-ui.row>
                        @endforeach
                    </x-ui.row-list>
                @endif
            @endif
        </div>
    </div>
</div>
