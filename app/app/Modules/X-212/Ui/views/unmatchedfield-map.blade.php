<div>
    <div class="unmatched-map-view p-4">
        <h2 class="text-lg font-bold text-ink">Field mapping</h2>
        @if($maps->isEmpty())
            <x-ui.empty-state heading="Nothing to map yet.">When we bring your business across from another system, anything of yours that has no obvious home here is listed for you to place.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($maps as $m)
                    <li class="py-2" wire:key="map-{{ $m->id }}">
                        <span class="font-semibold">{{ $m->source_field }}</span>
                        <span class="text-sm text-ink-2">{{ $m->target_entity }}</span>
                        <span class="text-sm text-ink-2">{{ $m->target_field }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
