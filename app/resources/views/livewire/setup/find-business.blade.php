<div>
    <x-setup.progress :current="\App\Enums\WizardStep::FindBusiness" />

    <h1 class="font-display text-3xl font-semibold">Find your business on Google</h1>

    <p class="mt-3 text-base">
        Open your business on Google Maps, copy the link, and paste it here. This is how
        we send customers to the right place when they leave you a review.
    </p>

    <form wire:submit="resolve" class="mt-6 space-y-3">
        <label for="pasted-url" class="block text-base font-medium">Your Google link</label>
        <input
            id="pasted-url"
            type="url"
            wire:model="pastedUrl"
            class="w-full rounded-[--radius-control] border border-rule px-3 py-2 text-base"
            placeholder="https://maps.app.goo.gl/..."
            autocomplete="off"
        >
        @error('pastedUrl') <p class="text-base text-alert">{{ $message }}</p> @enderror

        <x-ui.submit target="resolve" busy="Looking…">Find it</x-ui.submit>
    </form>

    @if ($candidate)
        <div class="mt-8 rounded-[--radius-card] border border-rule p-4">
            <p class="text-sm text-ink-2">Is this you?</p>
            <p class="mt-1 text-lg font-medium">{{ $candidate['display_name'] }}</p>
            <p class="text-base">{{ $candidate['formatted_address'] }}</p>

            <div class="mt-4 flex flex-wrap gap-3">
                <x-ui.button id="confirm-candidate" wire:click="confirm">Yes, that's us</x-ui.button>
                <x-ui.button variant="secondary" wire:click="discardCandidate">
                    Try a different link
                </x-ui.button>
            </div>
        </div>
    @endif

    @if (count($candidates) > 0)
        <div class="mt-8 space-y-3">
            <p class="text-base">We found more than one. Which is yours?</p>
            {{--
                empty-state: absent because the whole block is behind
                `count($candidates) > 0` — this list exists only to disambiguate
                a search that returned several, and a "no candidates" card here
                would appear beside the `$unresolvedReason` panel below, which
                is the screen's real answer to finding nothing.
            --}}
            @foreach ($candidates as $option)
                <button
                    type="button"
                    wire:click="choose('{{ $option['place_id'] }}')"
                    class="block w-full rounded-[--radius-card] border border-rule p-4 text-left"
                >
                    <span class="block text-lg font-medium">{{ $option['display_name'] }}</span>
                    <span class="block text-base">{{ $option['formatted_address'] }}</span>
                </button>
            @endforeach
        </div>
    @endif

    @if ($unresolvedReason)
        <div class="mt-8 rounded-[--radius-card] border border-rule p-4">
            <p class="text-base">{{ $unresolvedReason }}</p>
            <p class="mt-3 text-base">
                You can find your Place ID with Google's own
                <a href="https://developers.google.com/maps/documentation/places/web-service/place-id"
                   target="_blank" rel="noopener noreferrer" class="underline">Place ID Finder</a>,
                or skip this and come back to it.
            </p>
        </div>
    @endif

    <div class="mt-8">
        <x-ui.button variant="quiet" size="default" wire:click="skip">Skip for now</x-ui.button>
    </div>
</div>
