<div>
    <div class="matrix-view-container p-4">
        <h3 class="text-lg font-bold">Integration Matrix & Seam Visualizer</h3>
        @if(!$matrix)
            <p class="text-gray-500">No matrix generated yet. Run matrix:generate to inspect seams.</p>
        @else
            <p class="text-sm">Orphans: {{ $matrix->orphans_count }} | Violations: {{ $matrix->violations_count }}</p>
        @endif
    </div>
</div>
