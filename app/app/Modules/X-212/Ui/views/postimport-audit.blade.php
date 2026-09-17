<div>
    <div class="audit-view p-4">
        <h2 class="text-lg font-bold text-ink">Import rejections</h2>
        @if($rejects->isEmpty())
            <x-ui.empty-state heading="Nothing was rejected.">Every record an import could not bring across is listed here with the reason, so none of it goes missing quietly.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($rejects as $j)
                    <li class="py-2" wire:key="reject-{{ $j->id }}">
                        <span class="font-semibold">{{ $j->rejection_reason }}</span>,
                        <span class="text-sm text-ink-2 tabular-nums">record {{ $j->record_index }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
