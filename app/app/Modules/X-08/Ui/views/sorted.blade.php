<div>
    <div class="sorted-risk-view p-4">
        <h2 class="text-lg font-bold text-ink">Sorted Risk Rankings</h2>
        @forelse($sorted as $score)
            <div class="border border-rule p-2 mt-2 text-ink">
                Identifier: {{ $score->tenant_identifier }} - Risk Level: {{ $score->risk_level }}
            </div>
        @empty
            <p class="text-ink-2">No churn scores yet.</p>
        @endforelse
    </div>
</div>
