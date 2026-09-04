<x-surface.sample-state module="the form runtime shared by every site and the widget: capture, validate, write `Person` + `Conversation` + `Job`/`Message` as the form declares, brokered webhooks, spam and bot filtering, the abandon point *(with the pixel)*." screen="forms" />
<div>
    <div class="forms-view p-4">
        <h3 class="text-lg font-bold">Multi-Step Form Builder</h3>
        @if($forms->isEmpty())
            <p class="text-gray-500">No forms constructed yet.</p>
        @else
            <ul>
                @foreach($forms as $f)
                    <li>#{{ $f->id }}: {{ $f->form_name }} ({{ $f->slug }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
