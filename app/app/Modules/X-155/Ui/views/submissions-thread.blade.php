<div>
    <div class="submissions-thread-view p-4">
        <h2 class="text-lg font-bold text-ink">Form submissions</h2>
        <x-ui.toast kind="success" :message="$success" />
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
                        @if($s->is_spam)
                            <button type="button" wire:click="releaseSubmission({{ $s->id }})" class="bg-surface text-ink border rounded px-2 py-1 ml-2">Not spam</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
