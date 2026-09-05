<div>
    <x-surface.sample-state module="the form runtime shared by every site and the widget: capture, validate, write `Person` + `Conversation` + `Job`/`Message` as the form declares, brokered webhooks, spam and bot filtering, the abandon point *(with the pixel)*." screen="submissions_thread" />
    <div class="submissions-thread-view p-4">
        <h3 class="text-lg font-bold">Form Submissions Feed</h3>
        @if($submissions->isEmpty())
            <p class="text-gray-500">No submissions recorded.</p>
        @else
            <ul>
                @foreach($submissions as $s)
                    <li>
                        {{ $s->formDefinition->form_name }} — 
                        Person #{{ $s->person_id }} 
                        [{{ $s->is_spam ? 'SPAM' : 'VALID' }}] 
                        — {{ $s->created_at }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
