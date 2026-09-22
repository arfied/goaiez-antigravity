<div>
    <div class="p-4">
        <h2 class="text-xl font-bold text-ink">Every estimate, newest first</h2>

        @if($error)
            <div class="text-ink-2 bg-surface border p-2 mb-4 rounded">{{ $error }}</div>
        @endif
        @if($success)
            <div class="text-ink-2 bg-surface border p-2 mb-4 rounded">{{ $success }}</div>
        @endif

        <form wire:submit="draftEstimate" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border">
            <input type="text" wire:model="serviceName" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Service name">
            <input type="number" wire:model="quantity" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Quantity">
            <input type="number" wire:model="unitPriceCents" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Unit price (cents)">
            <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
        </form>

        @if($estimates->isEmpty())
            <x-ui.empty-state icon="○" heading="No estimates yet">
                When you draft an estimate for a customer, it appears here.
            </x-ui.empty-state>
        @else
            <input type="text" wire:model="customerSignature" class="border rounded p-2 text-ink flex-1 bg-surface mb-4 w-full" placeholder="Customer signature">
            <ul class="mt-3 space-y-2">
                @foreach($estimates as $est)
                    <li class="text-ink">{{ $est->estimate_number }} · ${{ number_format($est->total_cents / 100, 2) }} · {{ ucfirst($est->status) }}
                        @if($est->status === 'draft')
                            <button type="button" wire:click="sendEstimate({{ $est->id }})" class="bg-surface text-ink border rounded px-2 py-1 ml-2">Mark sent</button>
                        @endif
                        @if($est->status === 'sent')
                            <button type="button" wire:click="acceptEstimate({{ $est->id }})" class="bg-surface text-ink border rounded px-2 py-1 ml-2">Accept</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
