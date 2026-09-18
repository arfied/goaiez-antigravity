<div>
    <div class="whisper-panel p-4">
        <h2 class="text-lg font-bold text-ink">Call coaching</h2>
        @if($autopsies->isEmpty())
            <x-ui.empty-state heading="No coaching notes yet.">After a call, what went well and what to try next time is written down here.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($autopsies as $a)
                    <li class="py-2" wire:key="autopsy-{{ $a->id }}">
                        <span class="font-semibold">{{ $a->coaching_notes }}</span>
                        <span class="text-sm text-ink-2">{{ $a->sentiment }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
