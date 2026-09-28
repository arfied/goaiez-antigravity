<div>
    <div class="earnedvsgiven-panel-view p-4">
        <h2 class="text-lg font-bold text-ink">Discounts given</h2>
        @if((int) $totalDiscount === 0)
            <p class="text-ink-2">No discounts given yet.</p>
        @else
            <p class="text-ink">Total discount given: ${{ number_format($totalDiscount / 100, 2) }}</p>
        @endif

        <div class="mt-8 bg-surface p-4 border rounded">
            <h3 class="text-lg font-bold text-ink">Redeem Promotion</h3>
            <x-ui.toast kind="success" :message="$success" />
            <x-ui.toast kind="error" :message="$error" />
            <form wire:submit="redeem" class="flex flex-col gap-4 mt-4">
                <input type="text" wire:model="code" placeholder="Code" class="border rounded p-2 text-ink bg-surface">
                <input type="number" wire:model="customerId" placeholder="Customer ID" class="border rounded p-2 text-ink bg-surface">
                <input type="text" wire:model="orderId" placeholder="Order ID" class="border rounded p-2 text-ink bg-surface">
                <input type="number" wire:model="orderAmountCents" placeholder="Order Amount (cents)" class="border rounded p-2 text-ink bg-surface">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Redeem</button>
            </form>
        </div>
    </div>
</div>
