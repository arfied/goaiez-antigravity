<div>
    <div class="offline-inbox-view p-4">
        <h2 class="text-lg font-bold text-ink">Chat leads</h2>
        @if($leads->isEmpty())
            <p class="text-ink-2">No captured chat leads.</p>
        @else
            <ul>
                @foreach($leads as $l)
                    <li>#{{ $l->id }}: {{ $l->name }} ({{ $l->phone }}) [{{ $l->form_type }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
