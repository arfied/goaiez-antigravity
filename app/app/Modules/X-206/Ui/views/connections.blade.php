<div>
    <x-surface.sample-state module="⭐⭐ **Every platform and tenant credential — API keys, OAuth tokens, gateway credentials, calendar connections — scoped by OWNERSHIP, revealable to its owner, logged on reveal.** ⭐⭐⭐ **Its purpose is ANTI-LOCKOUT: every credential is retrievable by the person who owns it, so nobody is ever held hostage by a lost key — including us.** ⛔⛔ **It is NOT the card vault** *(that is `X-120`, in the PCI CDE)* **and NOT the secure field** *(`P-198`, tenant operational data)*. **Three stores, three scopes, deliberately separate.**" screen="connections" />
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
