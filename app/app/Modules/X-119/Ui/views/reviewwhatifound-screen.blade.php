<div>
    <div class="review-what-i-found p-4">
        <h2 class="text-lg font-bold text-ink">What we learned</h2>
        @if($facts->isEmpty())
            <x-ui.empty-state heading="Nothing waiting on you.">When the assistant picks something up about your business that it is not sure of, it waits here for you to say yes or no.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($facts as $f)
                    <li class="py-2" wire:key="fact-{{ $f->id }}">
                        <span class="font-semibold">{{ $f->key }}</span>
                        <span class="text-sm text-ink-2">{{ $f->value }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
