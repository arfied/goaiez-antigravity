<?php

declare(strict_types=1);

namespace App\Livewire\Setup;

use App\Enums\WizardStep;
use App\Livewire\Setup\Concerns\SetupStep;
use App\Models\Location;
use App\Services\Feedback\ReviewSigns;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Where the owner is shown how a customer actually reaches them.
 *
 * ⛔ **THIS WAS A STUB WITH NO CONTROL ON IT AT ALL, AND THE WIZARD ROUTED
 * EVERY NEW OWNER INTO IT** — 9156. Its whole body was *"Stub. Task 5 replaces
 * this with the real screen"*, its view was three lines, and neither the
 * progress bar above it nor `resources/views/components/setup/layout.blade.php`
 * carries a link — so an owner who arrived had no next, no skip, no back and no nav, and
 * `/setup` sent them straight back here on every later sign-in.
 * `FindBusiness::skip()` delivers them, and a green test asserted that redirect.
 * **The wizard could be finished only by typing a URL.**
 *
 * ⚠️ **CONTINUE, NOT SKIP, AND THE TWO ARE DIFFERENT PROMISES.** Step 2 offers
 * *"Skip for now"* because it asks for something and the owner may not have it
 * to hand. **This step collects nothing** — `feedback_pages` is minted at
 * provisioning, so the address already exists before the owner arrives — so
 * there is nothing to come back and finish, and a Skip would promise an errand
 * that does not exist. The answer recorded is `['seen' => true]`, which is the
 * honest thing that happened.
 *
 * ⛔ **THE QR IS REAL, AND TASK 5's "NO QR IMAGE" CONSTRAINT DIED BEFORE THIS
 * WAS BUILT.** `docs/superpowers/plans/2026-08-03-comp-02-onboarding-wizard.md`
 * §Task 5 forbids one on the grounds that *"`composer.json` carries no QR
 * library and `CLAUDE.md` requires approval before adding a dependency"*. That
 * was true when it was written and is not: `QrCodeSvg` (bare, not a `{@see}`:
 * Pint's `fully_qualified_strict_types` turns a docblock cross-reference into a
 * `use` statement, and an import nothing calls is a reader that is not one) is a
 * hand-rolled in-tree encoder under `app/Services/Feedback/`, {@see ReviewSigns} is its one chokepoint,
 * and `29` §7.2 names this step *"QR + review page"*. **No dependency is
 * added.** Shipping the link alone would have meant writing the plan's
 * justification into a docblock on a day it had stopped being true — 314–316's
 * shape, in the file that would be read next.
 *
 * ⚠️ **ONE CARD, NOT ONE PER LOCATION, AND THE REASON IS THE PRINT
 * STYLESHEET.** `resources/css/app.css` gives the whole printed page to
 * `.review-sign`, so two cards on one screen print as two overlapping sheets —
 * `Account\Locations` opens exactly one at a time for the same reason. A tenant
 * inside the wizard has exactly one location (`LocationProvisioner` is the only
 * thing in `app/` that makes one and provisioning makes one), so this is a
 * defensive branch rather than a routine one; when it is taken, the count is
 * said out loud and the account screen is where the rest are.
 *
 * ⚠️ **{@see ReviewSigns} RATHER THAN `FeedbackPage::query()`.** The plan's own
 * code block reads the model directly, and `ReviewsTest`'s *"nothing outside
 * the feedback service touches the slug directory"* fails the build on it.
 * Building it makes the feed entry, once per location for ever, which is that
 * class's stated contract rather than a side effect this screen invented.
 */
#[Layout('components.setup.layout')]
final class HowCustomersReach extends Component
{
    use SetupStep;

    public function mount(): void
    {
        $this->mountSetupStep();
    }

    /**
     * Record that the owner has seen their page, and move on.
     */
    public function continue(): void
    {
        $this->answerAndContinue(['seen' => true]);
    }

    public function render(ReviewSigns $signs): View
    {
        $locations = Location::query()->orderBy('id')->get();

        $first = $locations->first();

        return view('livewire.setup.how-customers-reach', [
            // Null on two different states, and the card tells them apart: no
            // location at all (nothing in `app/` produces it, and a factory-built
            // fixture does), and a location whose feedback page was never minted
            // — legacy, for which `LocationProvisioner` says in terms there is no
            // backfill.
            'sign' => $first instanceof Location ? $signs->for($first) : null,
            'otherLocationCount' => max(0, $locations->count() - 1),
        ]);
    }

    protected function step(): WizardStep
    {
        return WizardStep::HowCustomersReach;
    }
}
