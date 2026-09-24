<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Industry starting points</h1>
        <p class="mt-1 text-base text-ink-2">
            The design foundations applied when a site is first drafted in a specific industry.
        </p>
    </div>

    <div class="space-y-5">
        @foreach ($rows as $row)
            <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-lg font-semibold text-ink">{{ $row->family->label() }}</h2>
                    @if ($editing !== $row->family->value)
                        <button wire:click="edit('{{ $row->family->value }}')" type="button" class="rounded-[--radius-field] border border-rule px-4 py-2 text-ink text-sm">
                            Edit
                        </button>
                    @endif
                </div>

                @if ($editing === $row->family->value)
                    <div class="mt-5 space-y-5 border-t border-rule pt-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="palette_surface" class="block text-sm font-medium text-ink">Surface</label>
                                <input id="palette_surface" wire:model="palette.surface" type="text" class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-ink font-mono text-sm">
                            </div>
                            <div>
                                <label for="palette_ink" class="block text-sm font-medium text-ink">Ink</label>
                                <input id="palette_ink" wire:model="palette.ink" type="text" class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-ink font-mono text-sm">
                            </div>
                            <div>
                                <label for="palette_primary" class="block text-sm font-medium text-ink">Primary</label>
                                <input id="palette_primary" wire:model="palette.primary" type="text" class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-ink font-mono text-sm">
                            </div>
                            <div>
                                <label for="palette_accent" class="block text-sm font-medium text-ink">Accent</label>
                                <input id="palette_accent" wire:model="palette.accent" type="text" class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-ink font-mono text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="type_heading" class="block text-sm font-medium text-ink">Heading font stack</label>
                                <input id="type_heading" wire:model="typePairing.heading" type="text" class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-ink font-mono text-sm">
                            </div>
                            <div>
                                <label for="type_body" class="block text-sm font-medium text-ink">Body font stack</label>
                                <input id="type_body" wire:model="typePairing.body" type="text" class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-ink font-mono text-sm">
                            </div>
                        </div>

                        <div>
                            <label for="section_order" class="block text-sm font-medium text-ink">Section order (one block per line)</label>
                            <textarea id="section_order" wire:model="sectionOrder" rows="11" class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-ink font-mono text-sm"></textarea>
                        </div>

                        <div>
                            <label for="questions" class="block text-sm font-medium text-ink">Questions (one per line: key | label | hint | max | hero)</label>
                            <textarea id="questions" wire:model="questions" rows="6" class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-ink font-mono text-sm"></textarea>
                        </div>

                        <div class="flex gap-3">
                            <button wire:click="save" type="button" class="rounded-[--radius-field] bg-ink px-4 py-2 text-paper text-sm">
                                Save
                            </button>
                            <button wire:click="cancel" type="button" class="rounded-[--radius-field] border border-rule px-4 py-2 text-ink text-sm">
                                Cancel
                            </button>
                        </div>
                    </div>
                @else
                    <div class="mt-4 grid grid-cols-3 gap-6">
                        <div>
                            <h3 class="text-sm font-medium text-ink-2 mb-2">Palette</h3>
                            <ul class="space-y-2">
                                @foreach ($row->palette as $role => $hex)
                                    <li class="flex items-center gap-2 text-sm text-ink">
                                        <span class="w-6 h-6 rounded shadow-sm border border-rule" style="background-color: {{ $hex }}"></span>
                                        <span class="font-mono">{{ $hex }}</span>
                                        <span class="text-ink-3 capitalize">{{ $role }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-ink-2 mb-2">Typography</h3>
                            <ul class="space-y-2 text-sm text-ink">
                                <li>
                                    <span class="text-ink-3">Heading:</span>
                                    <span class="font-mono">{{ $row->type_pairing['heading'] }}</span>
                                </li>
                                <li>
                                    <span class="text-ink-3">Body:</span>
                                    <span class="font-mono">{{ $row->type_pairing['body'] }}</span>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-ink-2 mb-2">Section Order</h3>
                            <ol class="list-decimal pl-4 space-y-1 text-sm text-ink font-mono">
                                @foreach ($row->section_order as $block)
                                    <li>{{ $block }}</li>
                                @endforeach
                            </ol>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-sm font-medium text-ink-2 mb-2">Questions</h3>
                        <ol class="list-decimal pl-4 space-y-1 text-sm text-ink font-mono">
                            @foreach ($row->questions ?? [] as $q)
                                <li>{{ $q['key'] }} | {{ $q['label'] }} | {{ $q['hint'] }} | {{ $q['max'] }}{{ ($q['hero'] ?? false) ? ' | hero' : '' }}</li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </section>
        @endforeach
    </div>
</div>
