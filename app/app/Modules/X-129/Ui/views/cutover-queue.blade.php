<div>
    <div class="cutover-queue-view p-4">
        <h2 class="text-lg font-bold text-ink">Redirect queue</h2>
        @if($redirects->isEmpty())
            <p class="text-ink-2">No redirects mapped.</p>
        @else
            <ul>
                @foreach($redirects as $r)
                    <li>{{ $r->source_url }} &rarr; {{ $r->destination_url }} [{{ $r->status_code }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
