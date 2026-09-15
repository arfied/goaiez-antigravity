<div>
    <div class="network-map-view p-4">
        <h2 class="text-lg font-bold text-ink">Partner network</h2>
        @if($partners->isEmpty())
            <p class="text-ink-2">No partners in your network yet.</p>
        @else
            <ul>
                @foreach($partners as $partner)
                    <li>{{ $partner->company_name }} {{ $partner->category }} {{ $partner->territory_zip }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
