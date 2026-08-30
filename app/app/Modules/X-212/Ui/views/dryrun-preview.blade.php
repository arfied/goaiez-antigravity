<div>
    <div class="dryrun-preview-view p-4">
        <h3 class="text-lg font-bold">Dry-Run Preview</h3>
        @if($runs->isEmpty())
            <p class="text-gray-500">No migration runs executed.</p>
        @else
            <ul>
                @foreach($runs as $r)
                    <li>#{{ $r->id }}: {{ $r->source_system }} ({{ $r->imported_records }}/{{ $r->total_records }} valid)</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
