<div>
    <div class="rounded-[--radius-panel] border border-rule-strong bg-card p-4">
        <h2>Your business facts</h2>

        <p class="mt-2 text-base text-ink-2">
            Leave any box empty if you do not have it. We will not make one up.
        </p>

        <form wire:submit="save" class="mt-4 space-y-4">
            @foreach ($defs as $key => $def)
                <div>
                    <label for="{{ $key }}" class="block text-base text-ink">
                        {{ $def['label'] }}
                    </label>
                    @if ($def['hint'])
                        <p class="text-sm text-ink-2">{{ $def['hint'] }}</p>
                    @endif

                    @if ($key === 'description')
                        <textarea
                            id="{{ $key }}"
                            wire:model="facts.{{ $key }}"
                            rows="4"
                            class="mt-1 w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                        ></textarea>
                    @elseif ($key === 'years_in_business')
                        <input
                            id="{{ $key }}"
                            type="number"
                            inputmode="numeric"
                            wire:model="facts.{{ $key }}"
                            class="mt-1 w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                        />
                    @elseif ($key === 'industry')
                        <select id="{{ $key }}" wire:model="facts.{{ $key }}" class="mt-1 w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink">
                            <option value="">Use what Google says</option>
                            @foreach (\App\Enums\IndustryFamily::cases() as $family)
                                <option value="{{ $family->value }}">{{ $family->label() }}</option>
                            @endforeach
                        </select>
                    @else
                        <input
                            id="{{ $key }}"
                            type="text"
                            wire:model="facts.{{ $key }}"
                            class="mt-1 w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                        />
                    @endif

                    @error('facts.' . $key)
                        <p class="mt-1 text-base text-alert" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="mt-4">
                <x-ui.submit size="default" target="save" busy="Saving…">Save</x-ui.submit>
            </div>
        </form>
    </div>
</div>
