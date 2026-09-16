<div>
    <div class="preview-dest-view p-4">
        <h2 class="text-lg font-bold text-ink">Branded media</h2>
        @if($media->isEmpty())
            <p class="text-ink-2">No branded media generated.</p>
        @else
            <ul>
                @foreach($media as $m)
                    <li>#{{ $m->id }}: [{{ $m->destination }}] {{ $m->output_media_url }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
