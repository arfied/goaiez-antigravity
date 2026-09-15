<div>
    <div class="connections-container p-4">
        <h2 class="text-lg font-bold text-ink">Connections</h2>
        @if($credentials->isEmpty())
            <p class="text-ink-2">No credential connections configured.</p>
        @else
            <ul class="divide-y divide-rule">
                @foreach($credentials as $c)
                    <li class="py-2">
                        <span class="font-mono text-sm font-semibold">{{ $c->service_name }}</span>
                        <span class="text-xs text-ink-2">({{ $c->key_hint }})</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
