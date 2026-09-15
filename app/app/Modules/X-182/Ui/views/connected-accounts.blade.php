<div>
    <div class="connected-accounts-view p-4">
        <h2 class="text-lg font-bold text-ink">Connected accounts</h2>
        @if($accounts->isEmpty())
            <p class="text-ink-2">No social accounts connected yet.</p>
        @else
            <ul>
                @foreach($accounts as $account)
                    <li>{{ $account->platform }} {{ $account->account_handle }} — {{ $account->is_connected ? 'connected' : 'disconnected' }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
