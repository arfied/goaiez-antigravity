<div>
    <div class="connected-accounts-view p-4">
        <h2 class="text-lg font-bold text-ink">Connected accounts</h2>
        @if($accounts->isEmpty())
            <x-ui.empty-state heading="No social accounts connected">Connect a Facebook Page or an Instagram account to post through Zernio.</x-ui.empty-state>
        @else
            <ul>
                @foreach($accounts as $account)
                    <li>
                        @if($account->status === 'connected' && $account->account_ref !== null)
                            {{ $account->platform }} · {{ $account->account_handle }} — connected through Zernio
                            <button wire:click="disconnect({{ $account->id }})">Disconnect</button>
                        @elseif($account->status === 'pending' && $account->provider_profile_ref !== null)
                            {{ $account->platform }} — waiting for you to finish connecting
                        @elseif($account->status === 'disconnected')
                            {{ $account->platform }} · {{ $account->account_handle }} — disconnected{{ $account->last_error ? ': ' . $account->last_error : '' }}
                        @else
                            @if($account->is_connected && $account->account_ref === null)
                                {{ $account->platform }} {{ $account->account_handle }} — connected here only, not through Zernio. Connect it again to post.
                            @else
                                {{ $account->platform }} {{ $account->account_handle }} — disconnected
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-4">
            @if($locations->count() > 1)
                <select wire:model="locationId">
                    <option value="">Select a location</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
            @endif
            <button wire:click="connect('facebook')">Connect a Facebook Page</button>
            <button wire:click="connect('instagram')">Connect an Instagram account</button>
            <p>Instagram needs a Business or Creator account. A Page and its Instagram account are two separate connections.</p>
        </div>
    </div>
</div>
