<div>
    @if($error)
        <x-ui.error-panel :heading="$errorHeading ?? 'Could not connect'">
            {{ $error }}
        </x-ui.error-panel>
    @endif

    <x-ui.toast kind="success" :message="$success" />

    @if($connections->isEmpty())
        <x-ui.empty-state heading="No gateway connected yet" action="Connect" target="connect">
            No gateway has been connected on this account. Connecting one waits on the Stripe Connect redirect, which is not built in this checkout yet.
        </x-ui.empty-state>
    @else
        <x-ui.attention-card heading="Recorded gateways">
            <p class="text-sm text-ink-2 mb-4">These are recorded on this account. Connecting one and applying for a merchant account both wait on contracts that are not in this checkout yet, so the buttons below name what they wait on and change nothing.</p>

            <ul class="space-y-2">
                @foreach($connections as $conn)
                    <li class="flex items-center justify-between p-2 border rounded">
                        <span>{{ $conn->gateway_name }}</span>
                        <p class="text-sm text-ink-2">Recorded merchant account <span class="tabular-nums">{{ $conn->merchant_account_id }}</span>, which no charge is routed to yet</p>
                        <x-ui.status-pill :state="$conn->merchant_status === 'pending_kyc' ? 'attention' : 'ok'" :label="($conn->merchant_status ?? 'external_gateway') === 'pending_kyc' ? 'application recorded' : 'not applied'" />
                        
                        @if(($conn->merchant_status ?? 'external_gateway') === 'external_gateway')
                            <x-ui.button wire:click="applyForMerchant({{ $conn->id }})" wire:loading.attr="disabled">
                                Apply
                            </x-ui.button>
                        @endif
                    </li>
                @endforeach
            </ul>
            <x-ui.button variant="secondary" size="default" wire:click="connect" wire:loading.attr="disabled">Connect another gateway</x-ui.button>
        </x-ui.attention-card>
    @endif
</div>
