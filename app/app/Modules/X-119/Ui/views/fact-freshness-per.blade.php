<div>
    <div class="fact-freshness p-4">
        <h2 class="text-lg font-bold text-ink">Fact freshness</h2>
        @if($facts->isEmpty())
            <x-ui.empty-state heading="Nothing recorded yet.">What the assistant knows about your business appears here, oldest first, so you can see what may have gone out of date.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($facts as $f)
                    <li class="py-2" wire:key="fresh-{{ $f->id }}">
                        <span class="font-semibold">{{ $f->key }}</span>
                        <span class="text-sm text-ink-2">{{ $f->value }}</span>
                        <span class="text-sm text-ink-2 tabular-nums">{{ $f->updated_at }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
