<div>
    <livewire:x-124.chat-dock-every />
    
    <div class="max-w-3xl mx-auto px-4 py-8" wire:loading.class="opacity-50">
        <h1 class="text-2xl font-semibold text-ink mb-2">Daily Pricing Digest</h1>
        
        @if($items->isEmpty())
            <p class="text-ink-2 mb-8">Every pricing question today was answered</p>
            <x-ui.empty-state 
                heading="All good"
                {{-- action: href to the pricebook arrives with surfaces:generate --}}
                icon="✓">
                Every pricing question today was answered.
            </x-ui.empty-state>
        @else
            <p class="text-ink-2 mb-8">{{ $items->count() }} pricing questions we could not answer today</p>
            <div class="space-y-4">
                @foreach($items as $item)
                    <div class="bg-paper rounded-xl border border-rule p-4 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4" wire:key="item-{{ $item->id }}">
                        <div>
                            <h3 class="text-base font-medium text-ink flex items-center gap-2">
                                {{ $item->service_name }}
                                <x-ui.status-pill state="alert" label="{{ $item->refusal_count }} refusals" />
                            </h3>
                            <p class="text-sm text-ink-2 mt-1">Last refused: {{ $item->refusal_flagged_at->format('H:i') }}</p>
                        </div>
                        
                        <button wire:click="confirm({{ $item->id }})" class="h-10 px-4 text-sm font-medium text-white bg-accent hover:bg-accent-hover rounded-md transition-colors whitespace-nowrap">
                            Click to confirm
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
