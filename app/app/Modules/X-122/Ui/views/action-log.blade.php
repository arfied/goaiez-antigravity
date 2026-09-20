<div>

    <x-ui.systems-strip module="X-122" />

    <div class="action-log-container p-4">
        <h2 class="text-lg font-bold mb-4">Action Catalog & Dispatcher Log</h2>

        <div class="mb-4">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search actions..." class="border rounded p-2" />
        </div>

        <div wire:loading class="mb-4">
            <x-ui.skeleton label="Loading actions..." />
        </div>

        @if($errorMessage)
            <x-ui.error-panel heading="We could not load the action log.">{{ $errorMessage }}</x-ui.error-panel>
        @elseif($invocations->isEmpty())
            @if($search !== '')
                <x-ui.empty-state heading="No results found" description="No actions matched your search." />
            @else
                <x-ui.empty-state heading="No actions" description="No action invocations recorded." />
            @endif
        @else
            <ul class="divide-y divide-control">
                @foreach($invocations as $inv)
                    <li class="py-2 flex justify-between items-center">
                        <div>
                            <span class="font-mono text-sm">{{ $inv->action_name }}</span>
                            <span class="text-xs text-ink-dim">{{ $inv->created_at }} ({{ $inv->geo_city }}, {{ $inv->geo_country }})</span>
                            <x-ui.status-pill :status="$inv->status" />
                        </div>
                        <div>
                            @if($inv->status === 'completed')
                                <x-ui.button wire:click="reverse({{ $inv->id }})">Reverse</x-ui.button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            
            <div class="mt-4">
                {{ $invocations->links() }}
            </div>
        @endif
    </div>
</div>
