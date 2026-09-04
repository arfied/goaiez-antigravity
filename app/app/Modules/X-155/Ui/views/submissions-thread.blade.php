<div>
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
