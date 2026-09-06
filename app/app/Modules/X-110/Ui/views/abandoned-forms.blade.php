<div>
    
    <div wire:loading.delay>
        <x-ui.skeleton label="Loading abandoned forms..." />
    </div>

    <div wire:loading.remove>
        @if(isset($loadError) && $loadError)
            <x-ui.error-panel heading="Could not load abandoned forms">
                {{ $loadError }}
            </x-ui.error-panel>
        @elseif (count($abandonments) === 0)
            <x-ui.empty-state icon="📝" heading="No abandoned forms yet" action="Check install status" href="{{ route('account.pixel-install') }}">
                When visitors start filling out a form but don't submit, they'll appear here.
            </x-ui.empty-state>
        @else
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-ink">Abandoned Forms</h2>
                    @if ($topKiller)
                        <p class="text-sm text-ink-2">Highest friction: <span class="font-medium text-ink">'{{ $topKiller }}'</span> kills the most submissions ({{ $killerCount }}).</p>
                    @endif
                </div>
            
                <x-ui.row-list>
                    @foreach ($abandonments as $a)
                        <x-ui.row class="flex flex-col gap-3 sm:flex-row items-start sm:items-center p-4">
                            <div class="min-w-0 flex-1 w-full">
                                <p class="font-medium text-ink">
                                    <span class="font-mono text-xs">{{ substr((string)$a['visitor_id'], 0, 8) }}</span> — Quit {{ $a['form'] }} at '{{ $a['field'] }}'. 
                                    <span class="text-ink-2 font-normal">Happened {{ $a['time'] }}.</span>
                                </p>
                                @if (!$a['sent'])
                                    <div class="mt-3 w-full">
                                        <label for="msg-{{ $a['id'] }}" class="sr-only">Drafted message</label>
                                        <textarea 
                                            id="msg-{{ $a['id'] }}" 
                                            wire:model.defer="messages.{{ $a['id'] }}" 
                                            class="w-full rounded bg-paper border border-rule text-ink p-3 min-h-11 text-sm focus:outline-2"
                                            rows="2"
                                        ></textarea>
                                    </div>
                                @endif
                            </div>
                            <div class="flex sm:flex-col gap-2 w-full sm:w-auto mt-2 sm:mt-0 items-center sm:items-end">
                                @if ($a['sent'])
                                    <span class="text-ink-2 text-sm font-medium">Drafted (sending not wired yet)</span>
                                @else
                                    <x-ui.button variant="primary" size="default" type="button" class="flex-1 sm:flex-none" wire:click="recover({{ $a['id'] }})">Simulate Draft</x-ui.button>
                                @endif
                            </div>
                        </x-ui.row>
                    @endforeach
                </x-ui.row-list>
            </div>
        @endif
    </div>
</div>
