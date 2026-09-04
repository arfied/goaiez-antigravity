<div>
    <x-surface.sample-state module="C-Whatsapp" screen="template_status_card" />
    <div class="whatsapp-card-view p-4">
        <h3 class="text-lg font-bold">WhatsApp Templates</h3>
        @if($templates->isEmpty())
            <p class="text-gray-500">No WhatsApp templates submitted.</p>
        @else
            <ul>
                @foreach($templates as $t)
                    <li>#{{ $t->id }}: {{ $t->name }} [{{ $t->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
