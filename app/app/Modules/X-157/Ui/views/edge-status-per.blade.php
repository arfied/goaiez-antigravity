<div>
    <div class="edge-status-view p-4">
        <h2 class="text-lg font-bold text-ink">Edge deployments</h2>
        
        @if($errorMessage)
            <div class="bg-red-100 text-red-700 p-2 rounded mb-4">
                {{ $errorMessage }}
            </div>
        @endif

        @if($deployments->isEmpty())
            <p class="text-ink-2">No active edge deployments.</p>
        @else
            <ul class="space-y-4">
                @foreach($deployments as $d)
                    <li class="border p-4 rounded flex flex-col md:flex-row md:justify-between md:items-center">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm">{{ $d->deploy_hash }}</span>
                                <span class="text-xs px-2 py-1 rounded bg-surface">{{ $d->status }}</span>
                            </div>
                            <div class="text-sm mt-1 text-ink-2">
                                @if($d->edgeZone)
                                    {{ $d->edgeZone->domain_name }}
                                    @if($d->edgeZone->has_valid_ssl)
                                        <span class="text-green-600">(SSL Active)</span>
                                    @else
                                        <span class="text-ink-2">(No SSL)</span>
                                    @endif
                                @endif
                            </div>
                            <div class="text-sm mt-1 text-ink-2">
                                TTFB: {{ $d->measured_ttfb_ms }}ms / {{ $d->speed_budget_ms }}ms
                                • {{ $d->deployed_at ? $d->deployed_at->diffForHumans() : 'Unknown' }}
                            </div>
                        </div>
                        <div class="mt-4 md:mt-0 flex items-center">
                            <x-ui.button size="default" wire:click="rollback({{ $d->id }})">Rollback</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
