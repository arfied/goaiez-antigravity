<div>
    <div class="mb-6 sm:mb-8">
        <h2 class="text-sm font-semibold text-ink-2 uppercase tracking-wider mb-3">Credit Balances & Terms</h2>
        
        <div wire:loading>
            <x-ui.skeleton label="Loading credits..." />
        </div>
        
        <div wire:loading.remove>
            @if($loadError)
                <x-ui.error-panel heading="Could not load credits">
                    {{ $loadError }}
                </x-ui.error-panel>
            @elseif($isSample)
                <x-surface.sample-state module="generates EVERY invoice — ours and the tenant's; C-Billing is the subscription and metering LEDGER and hands data here (§139.1)" screen="credits" />
            @else
                <div class="mb-4">
                    <p class="text-3xl font-display font-bold text-ink">${{ number_format($limit / 100, 2) }}</p>
                    <div class="flex items-center gap-1">
                        <p class="text-xs text-ink-2">Total Limit</p>
                        <p class="text-xs text-ink-2">&middot;</p>
                        <p class="text-xs text-ink-2">${{ number_format($outstanding / 100, 2) }} outstanding</p>
                    </div>
                </div>
                @if($terms->isEmpty())
                    <x-ui.empty-state icon="💳" heading="No credit terms">
                        No credit terms have been issued.
                    </x-ui.empty-state>
                @else
                    <x-ui.row-list>
                        @foreach($terms as $term)
                            <x-ui.row>
                                <div class="flex-1 min-w-0 pr-4">
                                    <p class="text-sm font-medium text-ink truncate">{{ ucwords(str_replace('_', ' ', $term->terms_type)) }}</p>
                                    <p class="text-xs text-ink-2 truncate">Outstanding: ${{ number_format($term->current_outstanding_cents / 100, 2) }}</p>
                                </div>
                                <div class="text-sm font-semibold text-ink">
                                    ${{ number_format($term->credit_limit_cents / 100, 2) }}
                                </div>
                            </x-ui.row>
                        @endforeach
                    </x-ui.row-list>
                @endif
            @endif
        </div>
    </div>
</div>
