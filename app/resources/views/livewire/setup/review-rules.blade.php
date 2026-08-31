{{--
    COMP-02's threshold screen.

    ⚠️ THE DISCLOSURE AND THE CHOICES ARE COMPONENTS, AND THAT IS NOT A
    TIDY-UP. `/account` asks the identical question after the wizard (decision
    1143), and both screens record the same `ReviewRules::DISCLOSURE_VERSION` on
    the audit entry they write — so two copies of this text would make that
    stored version false on one of them, invisibly. Bump the constant whenever
    either changes.

    ⚠️ THERE WAS A THIRD COMPONENT — `x-reviews.gating-acknowledgement`, a
    required checkbox on any gated answer — and the owner removed it on
    2026-08-11 (decisions 2074, 2660). The disclosure it sat under stays: 2077
    records that the acknowledgement was one of the surfaces where a tenant was
    told which of these rules are theirs to move and which are a platform's, and
    removing it removed the explanation rather than the rule.
--}}

<div>
    <x-setup.progress :current="\App\Enums\WizardStep::ReviewRules" />

    <h1 class="font-display text-3xl font-semibold">Who should we ask for reviews?</h1>

    <p class="mt-3 text-base">
        After someone leaves you feedback, we can point them to your Google listing to post
        a public review. You decide who sees that invitation.
    </p>

    {{-- Required by COMP-02 to be here, open, and before the choice. --}}
    <x-reviews.gating-disclosure class="mt-8" />

    <form wire:submit="save" class="mt-8 space-y-4">
        <x-reviews.gating-choices model="rating" />

        <x-ui.submit target="save" busy="Saving…">Continue</x-ui.submit>
    </form>
</div>
