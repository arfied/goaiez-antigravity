<div>
    <livewire:x-124.chat-dock-every />
    
    <div class="max-w-4xl mx-auto px-4 py-8" wire:loading.class="opacity-50">
        <h2 class="text-2xl font-semibold text-ink mb-6">Pricebook</h2>

        @if(session()->has('error'))
            <x-ui.error-panel heading="We couldn't load the pricebook" class="mb-8">
                {{ session('error') }}
            </x-ui.error-panel>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="lg:col-span-2 bg-paper rounded-xl border border-rule overflow-hidden">
                <div class="p-6 border-b border-rule bg-surface">
                    <h2 class="text-lg font-medium text-ink mb-1">Callout Fee</h2>
                    <p class="text-sm text-ink-2 mb-4">The fee to send a technician.</p>
                    
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
                            <span class="text-sm text-ink">Deducted if proceeding</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="bg-paper rounded-xl border border-rule p-6">
                <h2 class="text-lg font-medium text-ink mb-4">Try a question</h2>
                <div class="flex gap-2 mb-4">
                    <input type="text" wire:model="testQuery" class="flex-1 h-10 px-3 py-2 bg-paper border border-rule rounded-md focus:outline-none focus:ring-2 focus:ring-accent" placeholder="e.g. Leak Repair">
                    <button wire:click="runTestQuote" class="h-10 px-4 text-sm font-medium text-paper bg-accent hover:bg-accent-hover rounded-md transition-colors">Ask</button>
                </div>
                @if($testQuoteResult)
                    <div class="p-3 bg-surface rounded border border-rule text-sm text-ink">
                        @if(isset($testQuoteResult['amount']))
                            Quote: ${{ number_format($testQuoteResult['amount'] / 100, 2) }}
                        @else
                            Refused: {{ $testQuoteResult['refusal_code'] ?? 'Unknown' }}
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="bg-paper rounded-xl border border-rule overflow-hidden mb-8">
            <div class="p-6 border-b border-rule bg-surface">
                <h2 class="text-lg font-medium text-ink mb-4">Add Item</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-ink-2 mb-1">Service Name</label>
                        <input type="text" wire:model="newServiceName" class="w-full h-10 px-3 py-2 bg-paper border border-rule rounded-md focus:outline-none focus:ring-2 focus:ring-accent" placeholder="Service">
                        @error('newServiceName')
                            <p class="mt-1 text-sm text-alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-2 mb-1">Price ($)</label>
                        <input type="number" step="0.01" wire:model="newPriceDollars" class="w-full h-10 px-3 py-2 bg-paper border border-rule rounded-md focus:outline-none focus:ring-2 focus:ring-accent" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-2 mb-1">Tax (%)</label>
                        <input type="number" step="0.01" wire:model="newTaxRatePct" class="w-full h-10 px-3 py-2 bg-paper border border-rule rounded-md focus:outline-none focus:ring-2 focus:ring-accent" placeholder="0.00">
                    </div>
                    <div>
                        <button wire:click="addItem" class="w-full h-10 text-sm font-medium text-paper bg-ink hover:bg-ink-2 rounded-md transition-colors">Add</button>
                    </div>
                </div>
            </div>
        </div>

        @if($items->isEmpty())
            <x-ui.empty-state 
                action="Add your first price — or confirm the ones we guessed"
                target="addItem"
                icon="+">
                Add your first price — or confirm the ones we guessed
            </x-ui.empty-state>
        @else
            <div class="space-y-4 mb-8">
                @foreach($items as $item)
                    <div class="bg-paper rounded-xl border border-rule p-4 sm:p-6" wire:key="item-{{ $item->id }}">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex flex-col gap-2 cursor-pointer" wire:click="tracePrice({{ $item->id }})">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-base font-medium text-ink">{{ $item->service_name }}</h3>
                                    @if($item->is_sample)
                                        <x-ui.status-pill state="attention" label="Sample" />
                                    @endif
                                    @if(isset($refusals[$item->id]))
                                        <span class="text-sm text-alert font-medium bg-alert-bg px-2 py-0.5 rounded">Needs a price</span>
                                    @endif
                                </div>
                                @if($traceItemId === $item->id)
                                    <div class="text-sm text-ink-2 bg-surface p-3 rounded border border-rule mt-2 space-y-1">
                                        <div><strong>Cents:</strong> {{ $item->price_cents }}</div>
                                        <div><strong>Tax:</strong> {{ $item->tax_rate_pct }}%</div>
                                        <div><strong>Confirmed:</strong> {{ $item->is_confirmed ? 'Yes' : 'No' }}</div>
                                        <div><strong>Updated:</strong> {{ $item->updated_at }}</div>
                                        <div><strong>Location book version:</strong> {{ collect($locations)->first() ? collect($locations)->first()->version : 1 }}</div>
                                    </div>
                                @endif
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-ink-2">$</span>
                                    <input type="number" step="0.01" wire:model="inlinePrices.{{ $item->id }}" class="w-24 h-10 px-3 py-2 bg-paper border border-rule rounded-md focus:outline-none focus:ring-2 focus:ring-accent">
                                </div>
                                
                                <button wire:click="confirmItem({{ $item->id }})" 
                                        class="h-10 px-3 text-sm font-medium text-paper bg-accent hover:bg-accent-hover rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed">Confirm</button>
                                <button wire:click="deleteItem({{ $item->id }})" class="h-10 px-3 text-sm font-medium text-alert hover:bg-alert-bg rounded-md transition-colors">Delete</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="bg-paper rounded-xl border border-rule overflow-hidden">
            <div class="p-6 border-b border-rule bg-surface">
                <h2 class="text-lg font-medium text-ink mb-4">Location Books</h2>
                <div class="space-y-4">
                    @foreach($locations as $loc)
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-medium text-ink">{{ $loc->location_name }}</span>
                                <span class="text-sm text-ink-2 ml-2">Version {{ $loc->version }}</span>
                            </div>
                            <button wire:click="bumpVersion('{{ addslashes($loc->location_name) }}')" class="h-8 px-3 text-sm font-medium text-ink border border-rule rounded hover:bg-surface transition-colors">Bump version</button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
