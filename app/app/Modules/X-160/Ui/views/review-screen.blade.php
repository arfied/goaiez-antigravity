<div>
    <div class="review-screen-view p-4">
        <h2 class="text-lg font-bold text-ink">Documents to review</h2>
        @if($pending->isEmpty())
            <x-ui.empty-state heading="Nothing is waiting to be read.">A document we have taken in but not yet pulled the facts out of waits here.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($pending as $p)
                    <li class="py-2" wire:key="pending-{{ $p->id }}">
                        <span class="font-semibold">{{ $p->title }}</span>
                        <span class="text-sm text-ink-2">{{ $p->mime_type }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
