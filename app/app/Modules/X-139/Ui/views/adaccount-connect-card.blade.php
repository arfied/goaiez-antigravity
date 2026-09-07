<div>
    <div class="adaccount-connect-view p-4">
        <h2 class="text-lg font-bold">Ad Platform Connections</h2>
        @if($connections->isEmpty())
            <x-ui.empty-state icon="🔌" heading="No ad accounts connected">
                Ad platform connections cannot be set up from this screen yet.
            </x-ui.empty-state>
        @else
            <ul>
                @foreach($connections as $c)
                    <li>{{ $c->platform }}: {{ $c->account_id }} ({{ $c->is_connected ? 'Connected' : 'Disconnected' }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
