<x-surface.sample-state module="Cloudflare zero-touch provisioning" screen="edge_status_per" />
<div>
    <div class="edge-status-view p-4">
        <h3 class="text-lg font-bold">Cloudflare Edge & SSL Deployments</h3>
        @if($deployments->isEmpty())
            <p class="text-gray-500">No active edge deployments.</p>
        @else
            <ul>
                @foreach($deployments as $d)
                    <li>#{{ $d->id }}: [{{ $d->status }}] TTFB: {{ $d->measured_ttfb_ms }}ms</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
