<div>
    <div class="connections-container p-4">
        <h3 class="text-lg font-bold">Credential Connections & Vault</h3>
        @if($credentials->isEmpty())
            <p class="text-gray-500">No credential connections configured.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($credentials as $c)
                    <li class="py-2">
                        <span class="font-mono text-sm font-semibold">{{ $c->service_name }}</span>
                        <span class="text-xs text-gray-500">({{ $c->key_hint }})</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
