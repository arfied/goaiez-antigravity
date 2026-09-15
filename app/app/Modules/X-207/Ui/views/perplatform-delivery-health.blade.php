<div>
    <h2 class="text-lg font-bold text-ink">Push health</h2>
    @if($platforms->isEmpty())
        <p class="text-ink-2">No devices registered.</p>
    @else
        <ul>
            @foreach($platforms as $p)
                <li>{{ $p->platform }}: {{ $p->devices }} devices</li>
            @endforeach
        </ul>
    @endif
    <ul>
        @foreach($statuses as $s)
            <li>{{ $s->status }}: {{ $s->n }}</li>
        @endforeach
    </ul>
</div>
