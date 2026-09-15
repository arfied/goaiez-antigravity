<div>
    <div class="rageclick-rate-view p-4">
        <h2 class="text-lg font-bold text-ink">Rage clicks</h2>
        @if($sessions->isEmpty())
            <p class="text-ink-2">No rage clicks recorded.</p>
        @else
            <ul>
                @foreach($sessions as $session)
                    <li>{{ $session->session_token }}: {{ $session->rage_clicks_count }} rage clicks [{{ $session->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
