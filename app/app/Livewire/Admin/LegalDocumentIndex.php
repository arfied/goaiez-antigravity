<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\LegalDocumentType;
use App\Services\Legal\LegalDocuments as Documents;
use App\Services\Legal\LegalDocumentState;
use App\Support\Admin\AdminAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The legal documents, where each one stands, and what is blocking a
 * launch.
 *
 * ## Why it exists
 *
 * Two gaps meet here, and neither is cosmetic.
 *
 * ⚠️ **`Admin\LegalDocuments` was unreachable from the console.** Its own
 * docblock says so — *"NOT IN AdminNav yet: the nav is a static list and this
 * screen takes a document type"* — and `AdminNav` names it in the same breath.
 * So the drafting screen existed and the only way to open it was to know
 * `/admin/legal/{doc}` and to know every slug. This index takes no
 * parameter, which is what lets decision 261's rule (the nav links only to what
 * exists) finally apply to it.
 *
 * ⚠️ **`is_placeholder` had no reader that could see the whole set.** `39`'s
 * checklist step 5 makes that column the input to a launch gate; `BaaRecords`
 * consults it for the BAA and nothing else ever has. See
 * `LegalDocuments::library()`.
 *
 * ## What it deliberately is not
 *
 * ⚠️ **NOT `38` PART 7'S LAUNCH CHECKLIST.** That screen is row 6–7's and covers
 * far more than legal text; building a corner of it here and calling it the
 * checklist is how a gate ends up believed-in and half-implemented (566). This
 * reports the one input it can honestly compute — which public documents still
 * serve placeholder text — and says whose gate that feeds rather than claiming
 * to be it.
 *
 * **No editing, and no publish button.** Both live one click away on the
 * per-document screen, which already refuses everything the service refuses. A
 * second surface that could publish would double the number of places `39`'s
 * two-person rule has to be got right.
 */
final class LegalDocumentIndex extends Component
{
    public function mount(): void
    {
        // Repeated on the component and not left to the route's `can:`
        // middleware. Decision 630: a route-gate test passes while `mount()` is
        // wide open, because `can:` refuses during route matching and the
        // component never runs — so the one that matters for a nested or
        // directly-mounted component is this line.
        $this->authorize(AdminAccess::GATE);
    }

    public function render(Documents $documents): View
    {
        $library = $documents->library();

        return view('livewire.admin.legal-document-index', [
            'library' => $library,
            // Computed here rather than in the template so the count and the
            // list cannot disagree — decision 258's gauge, where an arc and a
            // printed number were free to drift apart.
            'blocking' => array_values(array_filter(
                $library,
                fn (LegalDocumentState $state): bool => $state->blocksLaunch(),
            )),
            'baa' => $this->baa($library),
            // Derived, never typed into the template. "12 publicly served
            // documents" written as a literal beside a count computed from the
            // enum is two sources for one number, and the day another
            // document lands only one of them moves.
            'publicCount' => count(array_filter(
                $library,
                fn (LegalDocumentState $state): bool => $state->type->isPublic(),
            )),
        ]);
    }

    /**
     * The BAA's own state, which gates a different thing from the other twelve.
     *
     * `29` §12.2 gate 2 and `BaaRecords::recordExecution()`: a covered entity
     * cannot be onboarded against placeholder text. It is not part of the public
     * launch count because it is never served publicly, and reporting one number
     * for both audiences would be wrong for both.
     *
     * Named rather than derived from `isPublic()`. "The document that is not
     * public" happens to be the BAA today and would silently become a
     * different one the day another is added, and the wording beside it —
     * onboarding a covered entity — is the BAA's alone.
     *
     * @param  list<LegalDocumentState>  $library
     */
    private function baa(array $library): ?LegalDocumentState
    {
        foreach ($library as $state) {
            if ($state->type === LegalDocumentType::Baa) {
                return $state;
            }
        }

        return null;
    }
}
