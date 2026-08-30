<div>
    <div class="preview-dest-view p-4">
        <h3 class="text-lg font-bold">Branded Media Previews</h3>
        @if($media->isEmpty())
            <p class="text-gray-500">No branded media generated.</p>
        @else
            <ul>
                @foreach($media as $m)
                    <li>#{{ $m->id }}: [{{ $m->destination }}] {{ $m->output_media_url }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
