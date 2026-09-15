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
    </div>
</div>
