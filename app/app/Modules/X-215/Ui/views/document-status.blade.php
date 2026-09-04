<div>
    <x-surface.sample-state module="documents sent, viewed, commented on, signed and stored. ⛔ **The provider is a DRIVER and a CONFIG ROW** — DocuSign is the first driver, never a name in code." screen="document_status" />
    <div class="doc-status-view p-4">
        <h3 class="text-lg font-bold">Document Signature Status</h3>
        @if($docs->isEmpty())
            <p class="text-gray-500">No signable documents created.</p>
        @else
            <ul>
                @foreach($docs as $d)
                    <li>#{{ $d->id }}: {{ $d->title }} (v{{ $d->version }}) [{{ $d->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
