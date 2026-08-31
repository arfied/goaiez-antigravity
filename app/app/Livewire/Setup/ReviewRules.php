<?php

declare(strict_types=1);

namespace App\Livewire\Setup;

use App\Enums\WizardStep;
use App\Livewire\Setup\Concerns\SetupStep;
use App\Models\Location;
use App\Services\Reviews\ReviewGating;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * COMP-02's threshold screen — the wizard's one compliance gate.
 *
 * ⚠️ THIS IS THE STEP THE WHOLE WIZARD EXISTS TO REACH. `SetupFlow::
 * REQUIRED_STEPS` names only this one and `complete()` throws without it.
 *
 * ⚠️ THE DISCLOSURE IS ON THE SCREEN, NOT BEHIND AN "ADVANCED" TOGGLE. COMP-02
 * says so in those words, and the reason is that a warning an owner can choose
 * not to open is not a warning. It renders unconditionally, above the choice,
 * and a feature test asserts all four of its required subjects are present —
 * Google's policy, Trustpilot's terms, Yelp's position, and the FTC's.
 *
 * ⚠️ THE ACKNOWLEDGEMENT BOX IS GONE, BY THE OWNER'S RULING OF 2026-08-11
 * (decisions 2074, 2660). It was required for a gated answer and wrote
 * `autopilot_settings.gating_ack_at`, which `ReviewRouter` read as permission to
 * apply any threshold at all. The column and the gate are both removed, so a
 * threshold applies from the platform default of 4 with nothing acknowledged.
 * ⚠️ **The disclosure above it is not part of what was removed** — 2077 says
 * removing the acknowledgement removes the explanation and not the rule, so the
 * explanation stays as plain copy.
 *
 * ⚠️ THE LIVE THRESHOLD IS PRE-SELECTED, AND IT DID NOT USED TO BE. `$rating`
 * started empty on a first visit because nothing was in force until this screen
 * wrote an acknowledgement, so showing no selection was the honest rendering and
 * pre-selecting would have let an owner tap Continue without reading. After 2074
 * the seeded threshold of 4 applies from provisioning — so an empty radio would
 * now be telling the owner no rule is in force while their 3★ customers are not
 * being invited. Validation still refuses a submit with no answer, for a tenant
 * whose location has no gateable destination to read one from.
 *
 * ⚠️ FIVE ANSWERS RATHER THAN TWO, WHICH IS DECISION 1143 OVERRIDING 523.
 * COMP-02 asked for two stated options and 523 refused a picker on CLAUDE.md's
 * "opinionated defaults only"; the owner ruled the other way on 2026-08-07 and
 * 1186 fixed the range at 1–5 — restated unchanged at 2076, in the same ruling
 * that removed the acknowledgement. **The first answer is not a threshold of
 * 1** — it routes to `ReviewGating::inviteEveryone()`, which stores a 0, and
 * `gateAt()` refuses a 1 by name. See 1187 for why the two ends of that range
 * are different in kind.
 *
 * ⚠️ THE OWNER MAY CHANGE THIS AFTER THE WIZARD, ON `/account`. That is what
 * makes 1143's ruling true rather than a one-time question, and it is why the
 * disclosure and the choices are Blade components rather than markup living
 * here — two screens recording the same `disclosure_version` must be showing
 * the same words.
 */
#[Layout('components.setup.layout')]
final class ReviewRules extends Component
{
    use SetupStep;

    /**
     * The wording version a threshold change is recorded against.
     *
     * ⚠️ BUMP THIS WHENEVER THE DISCLOSURE TEXT CHANGES. It is stored on the
     * audit row so that "what did this owner actually read" is answerable years
     * later — the same reason `consent_records` carries `disclosure_version`
     * rather than relying on a timestamp. Reworded text under an unchanged
     * version makes every prior entry cite words nobody saw.
     *
     * ⚠️ **BUMPED FOR THE ACKNOWLEDGEMENT'S REMOVAL** (2074, 2663). The
     * checkbox went and the Yelp paragraph arrived, so every entry recorded
     * against `.1` cites a screen that no longer exists — which is precisely
     * what this constant is for. It records provenance on the audit row; it is
     * not the acknowledgement wearing a new name, and nothing gates on it.
     */
    public const string DISCLOSURE_VERSION = 'gating-2026-08-12.2';

    /**
     * The chosen rating, held as a string because that is what a radio submits.
     *
     * ⚠️ NOT A TYPED `?int`, AND THAT IS DELIBERATE. Livewire hydrates a public
     * property to its declared type before any validation runs, so a typed int
     * turns a hand-posted `rating=abc` into a TypeError — a 500 on a compliance
     * screen, where the honest answer is a validation message. Decision 396's
     * family: the framework's type juggling happens earlier than it looks.
     */
    public string $rating = '';

    public function mount(ReviewGating $gating): void
    {
        $this->mountSetupStep();

        // ⚠️ RESTORED FROM THE COLUMNS, NOT FROM THE WIZARD'S OWN ANSWER. The
        // owner may now change this on `/account` after finishing setup (1143),
        // so the wizard's recorded answer can be older than the truth — and a
        // screen that pre-selects a stale answer invites somebody to press
        // Continue and silently revert their own later change.
        $chosen = $gating->chosenThreshold(Location::query()->sole());

        if ($chosen !== null) {
            $this->rating = (string) $chosen;
        }
    }

    public function save(ReviewGating $gating): void
    {
        $validated = $this->validate([
            'rating' => ['required', Rule::in(self::ratings())],
        ], [
            'rating.required' => 'Choose one of the options so we know who to ask for reviews.',
            'rating.in' => 'Choose one of the options so we know who to ask for reviews.',
        ]);

        $rating = (int) $validated['rating'];
        $location = Location::query()->sole();
        $actor = 'user:'.Auth::id();

        if ($rating === ReviewGating::EVERYONE) {
            $gating->inviteEveryone($location, $actor);
        } else {
            $gating->gateAt($location, $rating, $actor, self::DISCLOSURE_VERSION);
        }

        // ⚠️ THE ANSWER IS RECORDED AFTER THE WRITE, NOT BEFORE IT. `SetupFlow::
        // mayComplete()` keys on this answer, so recording it first would let a
        // failure in `gateAt()` leave a wizard that believes this step is done
        // while the thresholds and the triage boundary it was meant to write
        // never landed — a location inviting at one number and triaging at
        // another, with nothing left to come back and fix it.
        $this->answerAndContinue([
            'rating' => $rating,
            'disclosure_version' => self::DISCLOSURE_VERSION,
        ]);
    }

    /**
     * @return list<string>
     */
    private static function ratings(): array
    {
        return array_map(strval(...), ReviewGating::choices());
    }

    public function render(): View
    {
        return view('livewire.setup.review-rules');
    }

    protected function step(): WizardStep
    {
        return WizardStep::ReviewRules;
    }
}
