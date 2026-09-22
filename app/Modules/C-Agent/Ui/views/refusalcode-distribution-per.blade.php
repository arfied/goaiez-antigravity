<div>
    <div class="refusal-dist-container p-4">
        <h2 class="text-lg font-bold text-ink">Agent refusals</h2>
        @if($refusals->isEmpty())
            <p class="text-ink-2">Zero refusals logged.</p>
        @else
            <ul>
                @foreach($refusals as $ref)
                    <li>{{ $ref->refusal_code }}: {{ $ref->reason }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
