<div>
    <div>
        <h2 class="text-lg font-bold text-ink">Do-not-text list</h2>
        @if($suppressions->isEmpty())
            <x-ui.empty-state heading="No numbers on the do-not-text list.">A number that asks you to stop, or that a carrier refuses, is recorded here and never texted again.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($suppressions as $s)
                    <li class="py-2">
                        <span class="font-mono text-sm">{{ $s->recipient_phone }}</span>
                        <span class="text-ink-2">{{ $s->reason }}</span>
                        <span class="text-sm text-ink-2">{{ $s->suppressed_at?->format('Y-m-d') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
