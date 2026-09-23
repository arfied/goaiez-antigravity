<div>
    <div class="today-overview p-4">
        <h2 class="text-lg font-bold text-ink">First wins</h2>
        @if($confirmSuccess)
            <div class="text-ink-2 bg-surface border p-2 mb-4 rounded">{{ $confirmSuccess }}</div>
        @endif
        @if($runs->isEmpty())
            <x-ui.empty-state heading="No onboarding runs yet.">A business that signs up appears here with the number it was given and how long it took to go live.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($runs as $r)
                    <li class="py-2" wire:key="run-{{ $r->id }}">
                        <span class="font-semibold">{{ $r->business_name }}</span>
                        <span class="text-ink-2">{{ $r->provisioned_number ?? 'no number yet' }}</span>
                        <span class="text-sm text-ink-2">{{ $r->status }}</span>
                        <span class="text-sm text-ink-2 tabular-nums">{{ $r->ttfm_ms }} ms</span>
                        @if($r->status !== 'confirmed')
                            <button type="button" wire:click="confirmRun({{ $r->id }})" class="bg-surface text-ink border rounded px-2 py-1 ml-2">Confirm</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
