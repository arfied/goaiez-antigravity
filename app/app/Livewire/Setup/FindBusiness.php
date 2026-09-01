<?php

declare(strict_types=1);

namespace App\Livewire\Setup;

use App\Enums\PlaceResolutionRule;
use App\Enums\WizardStep;
use App\Livewire\Setup\Concerns\SetupStep;
use App\Models\Location;
use App\Rules\GoogleLinkHost;
use App\Services\Places\PlaceCandidate;
use App\Services\Places\PlaceConfirmation;
use App\Services\Places\PlaceResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * GBP-01b's confirmation step — the screen `17` specified and nobody built.
 *
 * ⚠️ UNTIL THIS EXISTED, PlaceConfirmation::confirm() HAD NO CALLER IN app/.
 * It was referenced once, in a comment in TenantProvisioner explaining why
 * Google is seeded disabled. The consequence was not cosmetic: no tenant could
 * obtain a google_place_id, so by decision 312 the Google destination could
 * never be enabled, so the invite picker never offered Google — the flagship
 * destination of the entire review engine, unreachable in production. The sixth
 * instance of decision 272's shape, after Business::provision(),
 * autopilot_settings (377), feedback_pages, review_destinations and plugins.
 *
 * THE CANDIDATE IS HELD, NOT RE-RESOLVED. confirm() acts on the candidate that
 * resolve() produced and that the owner was shown. Re-resolving on confirm
 * would let a URL swapped between the two requests confirm a different place
 * than the one on screen — which is the exact failure `24` §1.2.3 exists to
 * prevent, rebuilt one layer up.
 *
 * ⚠️ $candidate, $candidates AND $resolvedUrl ARE #[Locked]. Livewire's
 * `HandleComponents::updateProperty()` only checks that a target is a
 * *declared public* property — it has no dependency on whether the Blade
 * template ever binds one with `wire:model`. Without `#[Locked]`, any
 * authenticated tenant user could `->set('candidate', [...])` an arbitrary
 * array over the ordinary client update protocol, never call resolve() at
 * all, and reach confirm() with a forged place id — bypassing GoogleLinkHost,
 * the whole resolver ladder, and the SSRF allowlist, none of which sit on
 * this path. `PlaceConfirmation`'s literal-`true` gate stops a *caller*
 * skipping confirmation; it does nothing to stop the *candidate* being
 * forged, because `isConfirmable()` only checks that a display name and
 * address are non-null; both were attacker-supplied. `#[Locked]` blocks
 * client-originated writes to these three properties while leaving resolve()
 * and choose() — plain PHP assignment from inside this class — free to set
 * them, so it does not conflict with hold-don't-re-resolve.
 *
 * $resolvedUrl is the same shape of fix, one property over: `confirm()` used
 * to read the live, `wire:model`-bound `$pastedUrl` when writing
 * `google_maps_url` and the audit row's `pasted_url` — both meant to record
 * the link that actually produced the confirmed candidate. Since `pastedUrl`
 * has to stay editable for the paste box to work, the URL that produced a
 * successful resolution is snapshotted into a locked property at that moment,
 * and confirm() reads the snapshot rather than whatever is currently sitting
 * in the input.
 *
 * $confirmed IS TYPED literal `true` ON THE SERVICE and this is the only caller.
 * Do not add a bool path. The narrowing is the enforcement (decision 220).
 */
#[Layout('components.setup.layout')]
final class FindBusiness extends Component
{
    use SetupStep;

    public string $pastedUrl = '';

    /**
     * The confirmable match, held between resolve() and confirm().
     *
     * @var array{place_id: string, rule: string, display_name: ?string, formatted_address: ?string, google_cid: ?string, categories: list<string>}|null
     */
    #[Locked]
    public ?array $candidate = null;

    /** @var list<array{place_id: string, rule: string, display_name: ?string, formatted_address: ?string, google_cid: ?string, categories: list<string>}> */
    #[Locked]
    public array $candidates = [];

    /**
     * The pasted URL that actually produced $candidate — see the class
     * docblock. Never read from directly by the view; confirm() is its only
     * reader.
     */
    #[Locked]
    public ?string $resolvedUrl = null;

    public ?string $unresolvedReason = null;

    public function mount(): void
    {
        $this->mountSetupStep();
    }

    public function resolve(PlaceResolver $resolver): void
    {
        // ⚠️ THE HOST ALLOWLIST IS PART OF THE RULE SET, NOT DECORATION.
        // GBP-01b's SSRF rejection is a build-failing test: a pasted URL on a
        // non-allowlisted host, or one redirecting to a private or loopback
        // address, must be rejected *without a request leaving the host*.
        // ResolvePlaceLinkRequest already carries exactly these rules and its
        // own docblock names "row 3's wizard" as the caller that would bypass
        // it — this is that caller. Reuse the rules rather than restating a
        // weaker set; a `url:http,https` on its own accepts evil.example.
        $this->validate([
            'pastedUrl' => [
                'required',
                'string',
                'max:2048',
                'url:http,https',
                new GoogleLinkHost,
            ],
        ], [
            'pastedUrl.url' => 'That does not look like a web link. Paste the whole link, starting with https://.',
            'pastedUrl.max' => 'That link is too long to be a Google link. Try the Share button on Google Maps.',
        ]);

        $this->candidate = null;
        $this->candidates = [];
        $this->resolvedUrl = null;
        $this->unresolvedReason = null;

        $outcome = $resolver->resolve($this->pastedUrl);

        if ($outcome->isResolved()) {
            /** @var PlaceCandidate $resolvedCandidate */
            $resolvedCandidate = $outcome->candidate();
            $this->candidate = $this->toArray($resolvedCandidate);
            $this->resolvedUrl = $this->pastedUrl;

            return;
        }

        if ($outcome->isAmbiguous()) {
            $this->candidates = array_map(fn (PlaceCandidate $c): array => $this->toArray($c), $outcome->candidates);
            $this->resolvedUrl = $this->pastedUrl;

            return;
        }

        $this->unresolvedReason = $this->explainUnresolved($outcome->reason ?? 'no_pattern_matched');
    }

    /**
     * Pick one of several candidates. Promotes it to the confirmable slot; it
     * still needs the owner to press "Yes, that's us".
     */
    public function discardCandidate(): void
    {
        $this->candidate = null;
    }

    public function choose(string $placeId): void
    {
        foreach ($this->candidates as $candidate) {
            if ($candidate['place_id'] === $placeId) {
                $this->candidate = $candidate;
                $this->candidates = [];

                return;
            }
        }
    }

    public function confirm(PlaceConfirmation $confirmation): void
    {
        if ($this->candidate === null || $this->resolvedUrl === null) {
            return;
        }

        $user = Auth::user();

        abort_if($user === null, 403);

        $confirmation->confirm(
            Location::query()->sole(),
            $this->fromArray($this->candidate),
            $this->resolvedUrl,
            'user:'.$user->id,
            true,
        );

        $this->answerAndContinue([
            'confirmed' => true,
            'place_id' => $this->candidate['place_id'],
        ]);
    }

    public function skip(): void
    {
        $this->answerAndContinue(['skipped' => true]);
    }

    public function render(): View
    {
        return view('livewire.setup.find-business');
    }

    protected function step(): WizardStep
    {
        return WizardStep::FindBusiness;
    }

    /**
     * ⚠️ `categories` RIDES IN THE LOCKED PROPERTIES, AND THAT IS LOAD-BEARING.
     * `PlaceConfirmation::confirm()` classifies the tenant from this list, so a
     * client-settable copy of it would let any authenticated tenant user hand
     * themselves a classification — in the harmless direction by asserting
     * `dentist`, and in the harmful one by *removing* the category that would
     * have raised them, which is a downgrade the service otherwise refuses
     * outright. The class docblock's `#[Locked]` reasoning covers this array
     * because it is a key inside `$candidate`; adding it as a separate public
     * property, or reading it off the request in confirm(), reopens exactly what
     * that attribute closed.
     *
     * @return array{place_id: string, rule: string, display_name: ?string, formatted_address: ?string, google_cid: ?string, categories: list<string>}
     */
    private function toArray(PlaceCandidate $candidate): array
    {
        return [
            'place_id' => $candidate->placeId,
            'rule' => $candidate->rule->value,
            'display_name' => $candidate->displayName,
            'formatted_address' => $candidate->formattedAddress,
            'google_cid' => $candidate->googleCid,
            'categories' => $candidate->categories,
        ];
    }

    /**
     * @param  array{place_id: string, rule: string, display_name: ?string, formatted_address: ?string, google_cid: ?string, categories?: list<string>}  $candidate
     */
    private function fromArray(array $candidate): PlaceCandidate
    {
        return new PlaceCandidate(
            placeId: $candidate['place_id'],
            rule: PlaceResolutionRule::from($candidate['rule']),
            displayName: $candidate['display_name'],
            formattedAddress: $candidate['formatted_address'],
            googleCid: $candidate['google_cid'],
            // Defaulted rather than required: a component whose state was
            // serialised by the previous deploy is rehydrated by this one, and a
            // missing key would be a TypeError on a wizard step mid-signup. An
            // absent list is the same "no signal" an unsearched row 1 produces.
            categories: $candidate['categories'] ?? [],
        );
    }

    /**
     * A resolver reason code, as an outcome-language sentence (`22`).
     *
     * `ResolutionOutcome`'s own docblock: the reason is "a short code... never
     * a sentence" — logged, not shown. `PublicAuditController::unavailable()`
     * is the existing precedent for translating one into copy a person reads,
     * and this is the same shape for the wizard rather than an API response.
     */
    private function explainUnresolved(string $reason): string
    {
        $messages = [
            'nothing_found' => 'We could not find a business at that link. Double-check it is the right one.',
            'no_pattern_matched' => 'We could not tell which business that link points to.',
            'budget_exhausted' => "We've used today's lookups. Try again tomorrow.",
            'search_failed' => 'We could not reach Google just now. Try again in a minute.',
        ];

        return $messages[$reason] ?? 'We could not use that link.';
    }
}
