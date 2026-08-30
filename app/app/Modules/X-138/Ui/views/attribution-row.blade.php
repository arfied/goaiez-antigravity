<div>
    <div class="attr-row-view p-4">
        <h3 class="text-lg font-bold">Touch Attribution Journey</h3>
        @if($queries->isEmpty())
            <p class="text-gray-500">No touch queries found.</p>
        @else
            <ul>
                @foreach($queries as $q)
                    <li>#{{ $q->id }}: Job #{{ $q->job_id }} [{{ $q->attribution_status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
