<div>
    <x-surface.sample-state module="offline event uploads to Google and Meta" screen="adaccount_connect_card" />
    <div class="adaccount-connect-view p-4">
        <h3 class="text-lg font-bold">Ad Platform Connections</h3>
        @if($connections->isEmpty())
            <p class="text-gray-500">No ad accounts connected.</p>
        @else
            <ul>
                @foreach($connections as $c)
                    <li>{{ $c->platform }}: {{ $c->account_id }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
