<div>
    @if($connections->isEmpty())
        <x-ui.empty-state heading="No connections" action="Connect" target="connect">
            Connect your gateway to receive payments.
        </x-ui.empty-state>
    @else
        <x-ui.attention-card heading="Connect Gateway">
            <p class="text-sm text-gray-600 mb-4">Manage your connections.</p>
            
            @if($error)
                <x-ui.error-panel heading="Could not connect">
                    {{ $error }}
                </x-ui.error-panel>
            @endif

            @if($success) <p class="text-base text-ink-2">{{ $success }}</p> @endif

            <ul class="space-y-2">
                @foreach($connections as $conn)
                    <li class="flex items-center justify-between p-2 border rounded">
                        <span>{{ $conn->gateway_name }}</span>
                        <p class="text-sm text-ink-2">Money lands in <span class="tabular-nums">{{ $conn->merchant_account_id }}</span></p>
                        <x-ui.status-pill :state="$conn->merchant_status === 'pending_kyc' ? 'attention' : 'ok'" :label="str_replace('_', ' ', $conn->merchant_status ?? 'external_gateway')" />
                        
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
