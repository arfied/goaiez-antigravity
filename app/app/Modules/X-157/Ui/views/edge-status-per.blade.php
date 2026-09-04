<div>
    <div class="edge-status-view p-4">
        <h3 class="text-lg font-bold">Cloudflare Edge & SSL Deployments</h3>
        
        @if($errorMessage)
            <div class="bg-red-100 text-red-700 p-2 rounded mb-4">
                {{ $errorMessage }}
            </div>
        @endif

        @if($deployments->isEmpty())
            <p class="text-gray-500">No active edge deployments.</p>
        @else
            <ul class="space-y-4">
                @foreach($deployments as $d)
                    <li class="border p-4 rounded flex flex-col md:flex-row md:justify-between md:items-center">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm">{{ $d->deploy_hash }}</span>
                                <span class="text-xs px-2 py-1 rounded bg-gray-100">{{ $d->status }}</span>
                            </div>
                            <div class="text-sm mt-1 text-gray-600">
                                @if($d->edgeZone)
                                    {{ $d->edgeZone->domain_name }}
                                    @if($d->edgeZone->has_valid_ssl)
                                        <span class="text-green-600">(SSL Active)</span>
                                    @else
                                        <span class="text-gray-400">(No SSL)</span>
                                    @endif
                                @endif
                            </div>
                            <div class="text-sm mt-1 text-gray-500">
                                TTFB: {{ $d->measured_ttfb_ms }}ms / {{ $d->speed_budget_ms }}ms
                                • {{ $d->deployed_at ? $d->deployed_at->diffForHumans() : 'Unknown' }}
                            </div>
                        </div>
                        <div class="mt-4 md:mt-0 flex items-center">
                            <button 
                                wire:click="rollback({{ $d->id }})" 
                                wire:loading.attr="disabled"
                                wire:target="rollback({{ $d->id }})"
                                class="px-4 py-2 bg-blue-600 text-white rounded text-sm disabled:opacity-50"
                            >
                                <span wire:loading.remove wire:target="rollback({{ $d->id }})">Rollback</span>
                                <span wire:loading wire:target="rollback({{ $d->id }})">Rolling back...</span>
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
