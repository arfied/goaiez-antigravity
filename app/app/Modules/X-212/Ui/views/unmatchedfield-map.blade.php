<div>
    <div class="p-4">
        <h2 class="text-lg font-bold text-ink">Field mapping</h2>

        @if($error)
            <div class="mt-4 p-4 bg-rule text-ink">{{ $error }}</div>
        @endif
        @if($success)
            <div class="mt-4 p-4 bg-paper text-ink">{{ $success }}</div>
        @endif

        @php
            $hasUnmapped = false;
            foreach ($runsData as $rd) {
                if (!empty($rd['unmapped'])) {
                    $hasUnmapped = true;
                    break;
                }
            }
        @endphp

        @if($maps->isEmpty() && !$hasUnmapped)
            <x-ui.empty-state heading="Nothing to map yet.">When we bring your business across from another system, anything of yours that has no obvious home here is listed for you to place.</x-ui.empty-state>
        @else
            @if($hasUnmapped)
                <div class="mt-6">
                    <h3 class="text-md font-semibold text-ink">Unmapped Fields</h3>
                    @foreach($runsData as $rd)
                        @if(!empty($rd['unmapped']))
                            <div class="mt-4 p-4 border border-rule">
                                <div class="mb-2 font-bold text-ink-2">Run #{{ $rd['run']->id }} ({{ $rd['run']->source_system }})</div>
                                <ul class="divide-y divide-rule">
                                    @foreach($rd['unmapped'] as $uf)
                                        <li class="py-2 flex items-center justify-between" wire:key="run-{{ $rd['run']->id }}-uf-{{ $uf }}">
                                            <span class="font-semibold text-ink">{{ $uf }}</span>
                                            <div class="flex items-center gap-2">
                                                <select class="border-rule text-ink p-1" wire:model="targetField.{{ $rd['run']->id }}-{{ $uf }}">
                                                    <option value="">Choose target...</option>
                                                    <option value="phone">phone</option>
                                                    <option value="first_name">first_name</option>
                                                    <option value="email">email</option>
                                                </select>
                                                <button type="button" class="px-2 py-1 bg-brand text-paper text-sm" wire:click="mapField({{ $rd['run']->id }}, '{{ $uf }}')">Map</button>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="mt-4">
                                    <button type="button" class="px-4 py-2 bg-brand text-paper" wire:click="recheck({{ $rd['run']->id }})">Re-check run</button>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            @if($maps->isNotEmpty())
                <div class="mt-8">
                    <h3 class="text-md font-semibold text-ink">Mapped Fields</h3>
                    <ul class="divide-y divide-rule mt-4 border border-rule">
                        @foreach($maps as $m)
                            <li class="py-2 px-4" wire:key="map-{{ $m->id }}">
                                <span class="font-semibold text-ink">{{ $m->source_field }}</span>
                                <span class="text-sm text-ink-2 px-2">→</span>
                                <span class="text-sm text-ink">{{ $m->target_entity }}.{{ $m->target_field }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif
    </div>
</div>
