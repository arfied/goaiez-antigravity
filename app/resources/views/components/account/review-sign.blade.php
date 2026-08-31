@props([
    'sign' => null,
    'locationId',
    'open' => false,
])

{{--
    A code for the counter — one location's feedback page, as something to print.

    ⛔ THE CARD ITSELF IS `components/feedback/review-card` AND THE COPY ON IT IS
    COMPLIANCE-GOVERNED — read that component before changing a word of it.
    It moved there at 9157, when the onboarding wizard's third step needed the
    same card: `29` §2 rule 1 on incentives, decision 2075 on never asking only
    the happy ones, `24` §2.3 on never pointing a printed code at a review site,
    and the printed address as the accessible path all live beside the words
    they govern. What stays here is this screen's own half — which location's
    card is open, and who may open one.

    ⚠️ THE CONTROLS THAT ARE NOT THE PRINT BUTTON RIDE IN THAT COMPONENT'S SLOT,
    outside `.review-sign`, which is what keeps them off the paper.

    ⛔ A BLADE COMPONENT DRIVEN BY `Account\Locations`, AND IT WAS A NESTED
    LIVEWIRE COMPONENT FOR HALF A DAY (6706). Livewire serialises every child
    into a `wire:snapshot` attribute whose memo contains the literal key
    `errors`, and `Content\AuthorBylineTest` asserts that this very screen shows
    the owner no such word — a rule about blame in copy (5844), landing on
    framework plumbing. The right answer is not to relax somebody else's
    compliance assertion so that a card can be printed: this panel holds no
    state of its own, so it did not need to be a component that has state.

    ⚠️ AND THE COLLISION IS A REAL FINDING RATHER THAN AN INCONVENIENCE: no
    child Livewire component can ever be added to this screen while that
    assertion reads raw markup, and the assertion already passes only because
    `Livewire::test()` blanks the ROOT snapshot — a real `->get()` would meet
    the same key on the parent. Recorded at 6706, not fixed here.
--}}

<div class="mt-3">
    @if ($open)
        @if ($sign === null)
            {{--
                ⚠️ AN HONEST ABSENCE RATHER THAN A BROKEN CARD (1220, 229). A
                location provisioned before `feedback_pages` existed has no
                slug, and there is no address to print. `LocationProvisioner`
                mints one for every location it makes and says in terms that
                there is no backfill, so this branch is legacy and defensive
                rather than routine — see 6552 for the same gap one table over.

                ⚠️ `ReviewSigns` DELIBERATELY DOES NOT MINT ONE. That belongs to
                the provisioner, which is the only caller that knows whether the
                location's name came from a verified listing or from the person
                who signed up (decision 334) — and this slug is permanent and
                about to be printed.
            --}}
            <div class="rounded-[--radius-panel] border border-rule-strong bg-card p-4">
                <p class="text-base text-ink">This location has no page for customers to rate yet.</p>
                <p class="mt-1 text-base text-ink-2">
                    Ask us and we will set one up — then the code is here waiting for you.
                </p>
            </div>
        @else
            <x-feedback.review-card :sign="$sign">
                <x-ui.button type="button" size="default" variant="secondary" wire:click="hideSign">
                    Put it away
                </x-ui.button>
            </x-feedback.review-card>
        @endif
    @else
        <x-ui.button type="button" size="default" variant="secondary" wire:click="showSign({{ $locationId }})">
            <span wire:loading.remove wire:target="showSign">Make a sign for your counter</span>
            <span wire:loading wire:target="showSign">Making it…</span>
        </x-ui.button>
    @endif
</div>
