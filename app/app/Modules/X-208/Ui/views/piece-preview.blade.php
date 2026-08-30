<div>
    <div class="piece-preview-view p-4">
        <h3 class="text-lg font-bold">Direct Postcard Previews</h3>
        @if($pieces->isEmpty())
            <p class="text-gray-500">No postcard pieces composed.</p>
        @else
            <ul>
                @foreach($pieces as $p)
                    <li>#{{ $p->id }}: {{ $p->recipient_address }} [{{ $p->postcard_format }}] ({{ $p->status }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
