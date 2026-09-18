<div>
    <livewire:x-124.chat-dock-every />
    
    <div class="max-w-3xl mx-auto px-4 py-8" wire:loading.class="opacity-50">
        <h2 class="text-2xl font-semibold text-ink mb-2">Daily Pricing Digest</h2>
        
        @if($items->isEmpty())
            <p class="text-ink-2 mb-8">Every pricing question was answered</p>
            <x-ui.empty-state 
                heading="All good"
                {{-- action: href to the pricebook arrives with surfaces:generate --}}
                icon="✓">
                Every pricing question was answered.
            </x-ui.empty-state>
        @else
            <p class="text-ink-2 mb-8">{{ $items->count() }} pricing {{ $items->count() === 1 ? 'question' : 'questions' }} we could not answer</p>
            <div class="space-y-4">
                @foreach($items as $item)
                    <div class="bg-paper rounded-xl border border-rule p-4 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4" wire:key="item-{{ $item->id }}">
                        <div>
                            <h3 class="text-base font-medium text-ink flex items-center gap-2">
                                {{ $item->service_name }}
                                <x-ui.status-pill state="alert" label="{{ $item->refusal_count }} {{ $item->refusal_count === 1 ? 'refusal' : 'refusals' }}" />
                                @if(isset($refusals[$item->id]))
                                    <span class="text-sm text-alert font-medium bg-alert-bg px-2 py-0.5 rounded">Needs a price</span>
                                @endif
                            </h3>
                            <p class="text-sm text-ink-2 mt-1">Last refused: {{ $item->refusal_flagged_at->format('j M H:i') }} &middot; ${{ number_format($item->price_cents / 100, 2) }}</p>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <span class="text-ink-2">$</span>
                            <input type="number" step="0.01" min="0" 
                                   wire:model="prices.{{ $item->id }}"
                                   class="w-32 h-10 px-3 py-2 bg-paper border border-rule rounded-md focus:outline-none focus:ring-2 focus:ring-accent"
                                   placeholder="0.00">
                            <button wire:click="confirm({{ $item->id }})" class="h-10 px-4 text-sm font-medium text-paper bg-accent hover:bg-accent-hover rounded-md transition-colors whitespace-nowrap">
                                Click to confirm
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
