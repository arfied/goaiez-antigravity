<div>
    <livewire:x-124.chat-dock-every />
    
    <div class="max-w-3xl mx-auto px-4 py-8" wire:loading.class="opacity-50">
        <div class="mb-8">
            <h2 class="text-2xl font-semibold text-ink mb-2">Price Confirmation</h2>
            <p class="text-ink-2">
                callout fee: {{ $isCalloutSet ? 'set' : 'not set' }} &middot; 
                {{ $unconfirmedCount }} left to review
            </p>
        </div>

        @if(session()->has('error'))
            <x-ui.error-panel heading="We couldn't confirm that" class="mb-8">
                {{ session('error') }}
            </x-ui.error-panel>
        @endif

        <div class="bg-paper rounded-xl border border-rule overflow-hidden mb-8">
            <div class="p-6 border-b border-rule bg-surface">
                <h2 class="text-lg font-medium text-ink mb-1">Callout Fee</h2>
                <p class="text-sm text-ink-2 mb-4">The fee to send a technician, before any work begins.</p>
                
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-ink-2">$</span>
                        <input type="number" step="0.01" min="0" 
                               wire:model="calloutFeeDollars"
                               class="w-32 h-10 px-3 py-2 bg-paper border border-rule rounded-md focus:outline-none focus:ring-2 focus:ring-accent"
                               placeholder="0.00">
                    </div>
                    
                    <label class="flex items-center gap-2 min-h-[40px]">
                        <input type="checkbox" wire:model="calloutFeeDeducted" class="w-5 h-5 rounded border-rule text-accent focus:ring-accent">
                        <span class="text-sm text-ink">Deducted if proceeding with work</span>
                    </label>
                </div>
            </div>
        </div>

        @if($items->isEmpty())
            <x-ui.empty-state 
                heading="Nothing left to confirm"
                icon="✓">
                All your prices have been confirmed.
            </x-ui.empty-state>
        @else
            <div class="space-y-4">
                @foreach($items as $item)
                    <div class="bg-paper rounded-xl border border-rule p-4 sm:p-6" wire:key="item-{{ $item->id }}">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                            <div class="flex items-center gap-3">
                                <h3 class="text-base font-medium text-ink">{{ $item->service_name }}</h3>
                                @if($item->is_sample)
                                    <x-ui.status-pill state="attention" label="Sample" />
                                @endif
                                
                                @if(isset($refusals[$item->id]))
                                    <span class="text-sm text-alert font-medium bg-alert-bg px-2 py-0.5 rounded">Needs a price</span>
                                @endif
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <span class="text-ink-2">$</span>
                                <input type="number" step="0.01" min="0" 
                                       wire:model="prices.{{ $item->id }}"
                                       class="w-32 h-10 px-3 py-2 bg-paper border border-rule rounded-md focus:outline-none focus:ring-2 focus:ring-accent"
                                       placeholder="0.00">
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-rule mt-4">
                            <button wire:click="deleteItem({{ $item->id }})" 
                                    class="h-10 px-4 text-sm font-medium text-alert hover:bg-alert-bg rounded-md transition-colors">
                                Not offered
                            </button>
                            
                            <button wire:click="confirm({{ $item->id }})" 
                                    class="h-10 px-4 text-sm font-medium text-paper bg-accent hover:bg-accent-hover rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                @if(isset($prices[$item->id]) && $prices[$item->id] != ($item->price_cents / 100))
                                    Fix price, then confirm
                                @else
                                    Confirm
                                @endif
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
