<div>
    <x-surface.sample-state module="⭐⭐ **Every platform and tenant credential — API keys, OAuth tokens, gateway credentials, calendar connections — scoped by OWNERSHIP, revealable to its owner, logged on reveal.** ⭐⭐⭐ **Its purpose is ANTI-LOCKOUT: every credential is retrievable by the person who owns it, so nobody is ever held hostage by a lost key — including us.** ⛔⛔ **It is NOT the card vault** *(that is `X-120`, in the PCI CDE)* **and NOT the secure field** *(`P-198`, tenant operational data)*. **Three stores, three scopes, deliberately separate.**" screen="reveal_log" />
    <div class="reveal-log-container p-4">
        <h3 class="text-lg font-bold">Audit Reveal Log</h3>
        @if($logs->isEmpty())
            <p class="text-gray-500">No credential reveal audit records.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($logs as $log)
                    <li class="py-2">
                        <span class="font-mono text-sm">{{ $log->service_name }}</span>
                        <span class="text-xs {{ $log->status === 'permitted' ? 'text-green-600' : 'text-red-600' }}">[{{ $log->status }}]</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
