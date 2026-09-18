<div>
    <div class="experiment-board-view p-4">
        <h2 class="text-lg font-bold">Content packs</h2>
        @forelse ($packs as $pack)
            <div>
                {{ $pack->pack_name }} {{ $pack->label_text }}
                @if ($pack->is_promoted)
                    proven on {{ $pack->fleet_sample_size }} businesses
                @else
                    still testing
                @endif
            </div>
        @empty
            No content pack has been tested for you yet
        @endforelse
    </div>
</div>
