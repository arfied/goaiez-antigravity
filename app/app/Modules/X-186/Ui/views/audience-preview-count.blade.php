<div>
    <div class="audience-preview-count-view p-4">
        <h2 class="text-lg font-bold">Target Audience Preview</h2>
        @if ($people === 0)
            <p>No one is in a campaign yet</p>
        @else
            <p>{{ $people }} people are in a running campaign</p>
            <p>{{ $count }} campaign runs are active</p>
        @endif
    </div>
</div>
