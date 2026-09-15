<div>
    <div class="submissions-thread-view p-4">
        <h2 class="text-lg font-bold text-ink">Form submissions</h2>
        @if($submissions->isEmpty())
            <p class="text-ink-2">No submissions recorded.</p>
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
