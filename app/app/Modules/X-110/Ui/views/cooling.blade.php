<div>
    <div wire:loading.delay>
        <x-ui.skeleton label="Loading cooling list..." />
    </div>

    <div wire:loading.remove>
        @if(isset($loadError) && $loadError)
            <x-ui.error-panel heading="Could not load cooling list">
                {{ $loadError }}
            </x-ui.error-panel>
        
        @elseif(count($visitors) === 0)
            <x-ui.empty-state icon="🧊" heading="Nobody cooling down right now" action="Check install status" href="{{ route('account.pixel-install') }}">
                When visitors drop off or abandon forms, they will appear here.
            </x-ui.empty-state>
        @else
            <div class="space-y-6">
                <h2 class="text-xl font-bold text-ink">Cooling Visitors</h2>

                <x-ui.row-list>
                    @foreach($visitors as $visitor)
                        <x-ui.row class="flex flex-col gap-3 sm:flex-row items-start sm:items-center p-4">
                            <div class="min-w-0 flex-1 w-full">
                                <p class="font-medium text-ink">
                                    <span class="font-medium">Visitor {{ $loop->iteration }}</span> — {{ $visitor['derivation'] }}. <span class="text-ink-2 font-normal">Quiet {{ $visitor['quiet_diff'] }}.</span>
                                </p>
                                <div class="mt-3 w-full">
                                    <label for="opener-{{ $visitor['visitor_id'] }}" class="sr-only">Drafted opener</label>
                                    <textarea 
                                        id="opener-{{ $visitor['visitor_id'] }}" 
                                        wire:model="openers.{{ $visitor['visitor_id'] }}" 
                                        class="w-full rounded bg-paper border border-rule text-ink p-3 min-h-11 text-sm focus:outline-2"
                                        rows="2"
                                    ></textarea>
                                </div>
                            </div>
                            <div class="flex sm:flex-col gap-2 w-full sm:w-auto mt-2 sm:mt-0">
                                <x-ui.button variant="secondary" size="default" type="button" class="flex-1 sm:flex-none" x-on:click="navigator.clipboard.writeText(document.getElementById('opener-{{ $visitor['visitor_id'] }}').value)">Copy</x-ui.button>
                                <x-ui.button variant="quiet" size="default" type="button" class="flex-1 sm:flex-none" wire:click="dismiss('{{ $visitor['visitor_id'] }}')">Hide for now</x-ui.button>
                            </div>
                        </x-ui.row>
                    @endforeach
                </x-ui.row-list>
            </div>
        @endif    </div>
</div>
