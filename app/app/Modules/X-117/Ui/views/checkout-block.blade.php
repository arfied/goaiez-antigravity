<div>
    <div class="checkout-block p-4">
        <h3 class="text-lg font-bold">Checkout</h3>
        @if($message)
            <div class="text-sm text-gray-800">{{ $message }}</div>
        @endif
        
        <div class="mt-4 flex gap-2">
            <button wire:click="authorise" class="px-4 py-2 bg-blue-500 text-white rounded">Authorise</button>
            <button wire:click="pay" class="px-4 py-2 bg-green-500 text-white rounded">Pay</button>
            <button wire:click="cancel" class="px-4 py-2 bg-red-500 text-white rounded">Cancel</button>
        </div>
    </div>
</div>
