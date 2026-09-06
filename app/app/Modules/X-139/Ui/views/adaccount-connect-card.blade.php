<div>
    <div class="adaccount-connect-view p-4">
        <h3 class="text-lg font-bold">Ad Platform Connections</h3>
        @if($connections->isEmpty())
            <p class="text-gray-500">No ad accounts connected.</p>
        @else
            <ul>
                @foreach($connections as $c)
                    <li>{{ $c->platform }}: {{ $c->account_id }} ({{ $c->is_connected ? 'Connected' : 'Disconnected' }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
