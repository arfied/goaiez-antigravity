<x-surface.sample-state module="recurring service plans" screen="members" />
<div>
    <div class="members-view p-4">
        <h3 class="text-lg font-bold">VIP Members</h3>
        @if($members->isEmpty())
            <p class="text-gray-500">No active members enrolled.</p>
        @else
            <ul>
                @foreach($members as $m)
                    <li>#{{ $m->id }}: Person #{{ $m->person_id }} [{{ $m->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
