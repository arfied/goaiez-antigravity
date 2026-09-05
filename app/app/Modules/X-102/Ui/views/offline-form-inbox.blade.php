<div>
    <x-surface.sample-state module="one line of JS" screen="offline_form_inbox" />
    <div class="offline-inbox-view p-4">
        <h3 class="text-lg font-bold">Offline & Capped Form Inbox</h3>
        @if($leads->isEmpty())
            <p class="text-gray-500">No captured chat leads.</p>
        @else
            <ul>
                @foreach($leads as $l)
                    <li>#{{ $l->id }}: {{ $l->name }} ({{ $l->phone }}) [{{ $l->form_type }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
