<x-surface.sample-state module="**local-first with SQLite on the device — a basement has no signal**" screen="sync_failure_rate" />
<div>
    <div class="sync-rate-view p-4">
        <h3 class="text-lg font-bold">Offline Device Sync Conflict Rate</h3>
        @if($conflicts->isEmpty())
            <p class="text-gray-500">Zero device sync conflicts.</p>
        @else
            <ul>
                @foreach($conflicts as $c)
                    <li>#{{ $c->id }}: Device {{ $c->device_id }} (v{{ $c->client_version }} vs v{{ $c->server_version }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
