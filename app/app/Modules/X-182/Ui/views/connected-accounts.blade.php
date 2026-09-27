<div>
    <div class="connected-accounts-view p-4">
        <h2 class="text-lg font-bold text-ink">Connected accounts</h2>
        @if($accounts->isEmpty())
            <x-ui.empty-state heading="No social accounts connected">Connecting a social account is not built here yet; this list fills only once an account exists.</x-ui.empty-state>
        @else
            <ul>
                @foreach($accounts as $account)
                    <li>{{ $account->platform }} {{ $account->account_handle }} — {{ $account->is_connected ? 'connected' : 'disconnected' }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
