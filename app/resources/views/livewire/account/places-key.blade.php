<div class="space-y-8">
    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Which key is in use</h2>
        <p class="mt-2 text-base text-ink-2">
            @if ($inUse === 'tenant')
                Yours (ends …{{ $hint }})
            @else
                The platform's shared key
            @endif
        </p>

        @if ($inUse === 'tenant')
            <div class="mt-4">
                <x-ui.button wire:click="forget" type="button" size="default" variant="secondary">
                    Stop using my key
                </x-ui.button>
            </div>
        @endif
    </div>

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Use your own key</h2>
        <p class="mt-2 text-base text-ink-2">
            With your own key, Google bills your project directly and searches are not counted against the shared allowance.
        </p>

        <form wire:submit="save" class="mt-4 space-y-3">
            <div>
                <label for="candidate" class="block text-sm font-medium text-ink">Your API key</label>
                <input
                    type="password"
                    id="candidate"
                    wire:model="candidate"
                    class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                >
                @error('candidate')
                    <p class="mt-1 text-sm text-ink">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-ui.submit size="default" target="save" busy="Saving…">Validate and save</x-ui.submit>
            </div>
        </form>
    </div>

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">How to get a key</h2>
        <ol class="mt-2 list-decimal pl-5 text-base text-ink-2 space-y-2">
            <li>Google Cloud Console</li>
            <li>create or pick a project</li>
            <li>APIs & Services</li>
            <li>enable "Places API (New)"</li>
            <li>Credentials → Create API key</li>
            <li>restrict it to the Places API (New)</li>
            <li>copy it here</li>
        </ol>
    </div>
</div>
