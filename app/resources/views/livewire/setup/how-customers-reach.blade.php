{{--
    `29` §7.2's third step — "QR + review page … generated immediately".

    ⛔ THIS SCREEN HAD NO CONTROL ON IT AT ALL UNTIL 9156, AND THE WIZARD ROUTED
    EVERY NEW OWNER INTO IT. Three lines: a progress bar whose steps are not
    links, inside a layout that carries no navigation by design. No next, no
    skip, no back. Read `App\Livewire\Setup\HowCustomersReach` for the whole
    account, including why the control below says Continue rather than Skip.

    ⚠️ NOTHING IS COLLECTED HERE — the page and its address already exist, minted
    at provisioning — so this screen only ever SHOWS. That is why there is no
    form, and why the empty state is a sentence rather than an action.
--}}

<div>
    <x-setup.progress :current="\App\Enums\WizardStep::HowCustomersReach" />

    <h1 class="font-display text-3xl font-semibold">How customers reach you</h1>

    @if ($sign === null)
        {{--
            ⚠️ AN HONEST ABSENCE RATHER THAN A BROKEN CARD (1220, 229), and the
            same words `components/account/review-sign` uses for the same state.
            A tenant inside the wizard always has a location and that location
            always has a page — `LocationProvisioner` mints both in the signup
            transaction — so this branch is legacy and defensive. Minting one
            here is refused on decision 334's rule: only the provisioner's caller
            knows whether the name it would build a permanent public slug from is
            a verified business name or the person's own.
        --}}
        <p class="mt-3 text-base">
            We're still setting up the page your customers rate you on. Nothing to do —
            it will be waiting on your account screen when it's ready.
        </p>
    @else
        <p class="mt-3 text-base">
            This is your review page. Give a customer this code or this address after you
            serve them, and they can tell you how it went in about twenty seconds.
        </p>

        <p class="mt-2 text-base text-ink-2">
            Whatever they say comes to you first. Nothing is posted anywhere without them
            choosing to.
        </p>

        <div class="mt-8">
            <x-feedback.review-card :sign="$sign" />
        </div>

        @if ($otherLocationCount > 0)
            {{--
                ⚠️ ONE CARD, NOT ONE PER LOCATION, AND THE REASON IS THE PRINT
                STYLESHEET. `resources/css/app.css` gives the whole printed page
                to `.review-sign`, so two cards on one screen print as two
                overlapping sheets — `Account\Locations` opens exactly one at a
                time for the same reason. Saying the count out loud beats
                silently showing one of several.
            --}}
            <p class="mt-6 text-base text-ink-2">
                You have {{ $otherLocationCount + 1 }} locations, and each one has its own
                page and its own code. The rest are on your
                <a href="{{ route('account.locations') }}" class="underline hover:text-ink">locations screen</a>.
            </p>
        @endif
    @endif

    <div class="mt-8">
        <x-ui.button wire:click="continue">Continue</x-ui.button>
    </div>
</div>
