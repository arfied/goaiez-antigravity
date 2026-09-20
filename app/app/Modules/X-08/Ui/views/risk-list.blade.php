<div>
    <div class="risk-list-view p-4">
        <h2 class="text-lg font-bold">Churn Risk List</h2>
        
        @forelse($scores as $score)
            <div class="border p-2 mt-2">
                Identifier: {{ $score->tenant_identifier }} - Risk Level: {{ $score->risk_level }}
            </div>
        @empty
            <p>No churn scores yet.</p>
        @endforelse

        <div class="mt-4 border-t pt-4">
            <h3 class="font-bold">Evaluate Tenant</h3>
            
            @if($error)
                <div class="text-red-500 mb-2">{{ $error }}</div>
            @endif
            
            @if($success)
                <div class="text-green-500 mb-2">{{ $success }}</div>
            @endif

            <form wire:submit="evaluate" class="space-y-4 max-w-sm mt-2">
                <div>
                    <label class="block text-sm">Tenant Identifier</label>
                    <input type="text" wire:model="tenantIdentifier" class="border p-2 w-full">
                </div>
                <div>
                    <label class="block text-sm">Login Decay Days</label>
                    <input type="number" wire:model="loginDecayDays" class="border p-2 w-full">
                </div>
                <div>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" wire:model="roiOpenRateRising">
                        <span class="text-sm">ROI Open Rate Rising</span>
                    </label>
                </div>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Evaluate</button>
            </form>
        </div>
    </div>
</div>
