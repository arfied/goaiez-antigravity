<div>
    <div class="unsupported-log-view p-4">
        <h3 class="text-lg font-bold">Unsupported Utterance Logs</h3>
        @if($logs->isEmpty())
            <p class="text-gray-500">No unsupported utterances recorded.</p>
        @else
            <ul>
                @foreach($logs as $l)
                    <li>#{{ $l->id }}: "{{ $l->utterance }}" -> "{{ $l->response_returned }}"</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
