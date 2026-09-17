<div>
    <div class="dryrun-preview-view p-4">
        <h2 class="text-lg font-bold text-ink">Dry-run preview</h2>
        @if($runs->isEmpty())
            <x-ui.empty-state heading="No imports yet.">When a business is brought across from another system, each attempt appears here with how much of it landed.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($runs as $r)
                    <li class="py-2" wire:key="run-{{ $r->id }}">
                        <span class="font-semibold">{{ $r->source_system }}</span>,
                        <span class="text-sm text-ink-2">{{ $r->status }}</span>,
                        <span class="text-sm text-ink-2 tabular-nums">{{ $r->imported_records }} of {{ $r->total_records }} records</span>,
                        <span class="text-sm text-ink-2 tabular-nums">{{ $r->rejected_records }} rejected</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
