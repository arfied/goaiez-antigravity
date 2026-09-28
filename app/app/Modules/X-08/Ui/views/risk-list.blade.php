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
            
            <x-ui.toast kind="error" :message="$error" />
            
            <x-ui.toast kind="success" :message="$success" />

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
                <button type="submit" class="bg-surface text-ink border rounded p-2">Evaluate</button>
            </form>
        </div>
    </div>
</div>
