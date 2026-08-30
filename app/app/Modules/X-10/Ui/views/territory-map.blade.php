<div>
    <div class="territory-map-view p-4">
        <h3 class="text-lg font-bold">Territory & Geocode Map</h3>
        @if($territories->isEmpty())
            <p class="text-gray-500">No territories drawn on map.</p>
        @else
            <ul>
                @foreach($territories as $t)
                    <li>#{{ $t->id }}: {{ $t->name }} (Staff: {{ $t->assigned_staff_id ?? 'Unassigned' }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
