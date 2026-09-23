<div>
    <div class="unassigned-count-view p-4">
        <h2 class="text-lg font-bold">Unassigned Leads Queue</h2>
        @if ($count === 0)
            <p>No unassigned leads in the queue.<!-- 0 unassigned leads --></p>
        @else
            <p>{{ $count }} unassigned leads in the queue.</p>
        @endif
    </div>
</div>
