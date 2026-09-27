<div>
    <div class="reveal-container p-4">
        <h2 class="text-lg font-bold text-ink">Reveal credential</h2>
        <p class="text-ink-2">Every reveal is logged with who, when and which key.</p>
        <x-ui.toast kind="error" :message="$error" />
        @if($revealedSecret !== null)
            <p class="text-ink-2">Shown once, for this page only:</p>
            <pre class="font-mono text-sm text-ink">{{ $revealedSecret }}</pre>
        @endif
        @if($credentials->isEmpty())
            <p class="text-ink-2">No credentials to reveal.</p>
        @else
            <ul class="divide-y divide-rule">
                @foreach($credentials as $c)
                    <li class="py-2">
                        <span class="font-mono text-sm">{{ $c->service_name }}</span>
                        <span class="text-ink-2">({{ $c->key_hint }})</span>
                        <x-ui.button size="default" variant="quiet" wire:click="reveal({{ $c->id }})">Reveal</x-ui.button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
