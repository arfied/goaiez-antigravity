<div>
    @if($connections->isEmpty())
        <x-ui.empty-state heading="No connections" action="Connect" target="connect">
            Connect your gateway to receive payments.
        </x-ui.empty-state>
    @else
        <x-ui.attention-card heading="Connect Gateway">
            <p class="text-sm text-gray-600 mb-4">Manage your connections.</p>
            
            @if($error)
                <x-ui.error-panel heading="Could not connect" retry="">
                    {{ $error }}
                </x-ui.error-panel>
            @endif

            <ul class="space-y-2">
                @foreach($connections as $conn)
                    <li class="flex items-center justify-between p-2 border rounded">
                        <span>{{ $conn->gateway_name }}</span>
                        <x-ui.status-pill :state="$conn->merchant_status === 'pending_kyc' ? 'attention' : 'ok'" label="{{ str_replace('_', ' ', $conn->merchant_status ?? 'external_gateway') }}" />
                        
                        @if(($conn->merchant_status ?? 'external_gateway') === 'external_gateway')
                            <x-ui.button wire:click="applyForMerchant({{ $conn->id }})" wire:loading.attr="disabled">
                                Apply
                            </x-ui.button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-ui.attention-card>
    @endif
</div>
