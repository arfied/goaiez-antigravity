<div>
    <div class="refusal-dist-container p-4">
        <h3 class="text-lg font-bold">Refusal Code Distribution</h3>
        @if($refusals->isEmpty())
            <p class="text-gray-500">Zero refusals logged.</p>
        @else
            <ul>
                @foreach($refusals as $ref)
                    <li>{{ $ref->refusal_code }}: {{ $ref->reason }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
