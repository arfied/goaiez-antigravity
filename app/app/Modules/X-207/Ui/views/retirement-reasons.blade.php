<div>
    <div class="retirement-reasons-view p-4">
        <h2 class="text-lg font-bold text-ink">Retired devices</h2>
        @if($tokens->isEmpty())
            <p class="text-ink-2">No retired devices yet.</p>
        @else
            <ul>
                @foreach($tokens as $token)
                    <li>{{ $token->platform }} — {{ $token->retirement_reason }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
