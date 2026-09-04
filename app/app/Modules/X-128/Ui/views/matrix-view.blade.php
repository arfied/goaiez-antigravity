<div>
    <x-surface.sample-state module="reads every module's `@renders matrix_view` *(derived from SCREENS, 2026-08-27 — names only; the JSON schema each block carries is turn 69's)*" screen="matrix_view" />
    <div class="matrix-view-container p-4">
        <h3 class="text-lg font-bold">Integration Matrix & Seam Visualizer</h3>
        @if(!$matrix)
            <p class="text-gray-500">No matrix generated yet. Run matrix:generate to inspect seams.</p>
        @else
            <p class="text-sm">Orphans: {{ $matrix->orphans_count }} | Violations: {{ $matrix->violations_count }}</p>
        @endif
    </div>
</div>
