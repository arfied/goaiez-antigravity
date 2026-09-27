<div>
    <div class="matrix-view-container p-4">
        <h3 class="text-lg font-bold">Integration Matrix & Seam Visualizer</h3>

        <x-ui.toast kind="success" :message="$success" />

        <form wire:submit="generateMatrix" class="mb-6 bg-surface p-4 rounded mt-4 border">
            <button type="submit" class="bg-surface text-ink border rounded p-2">Generate matrix</button>
        </form>

        @if(!$matrix)
            <p class="text-gray-500">No matrix generated yet. Generate one to inspect seams.</p>
        @else
            <p class="text-sm">Orphans: {{ $matrix->orphans_count }} | Violations: {{ $matrix->violations_count }}</p>
        @endif
    </div>
</div>
