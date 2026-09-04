<div>
    <x-ui.attention-card title="Connect Stripe">
        <p class="text-sm text-gray-600 mb-4">Connect your account to receive payments.</p>
        
        @if($error)
            <x-ui.error-panel heading="Could not connect" retry="applyForMerchant">
                {{ $error }}
            </x-ui.error-panel>
        @endif

        @if($status === 'applied')
            <x-ui.status-pill state="ok" label="Application in progress" />
        @else
            <x-ui.button wire:click="applyForMerchant" wire:loading.attr="disabled">
                Connect Stripe
            </x-ui.button>
        @endif
    </x-ui.attention-card>
</div>
