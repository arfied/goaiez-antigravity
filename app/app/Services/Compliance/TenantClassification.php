<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Enums\DataClassification;
use App\Models\Business;
use App\Services\AuditService;
use App\Services\Legal\BaaRecords;
use App\Services\Warehouse\DerivedPurge;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The one place this application decides a business's data classification.
 *
 * ⚠️ WHY THIS EXISTS AT ALL. `businesses.data_classification` drives the hardest
 * boundary in this system after tenancy — decisions 421–423 built a gate so a
 * PHI tenant's review text never reaches a third-party model, and `29` §2
 * rule 24 hangs the separate schema, role and KMS key off the same column. Until
 * this class, **the only writers of that column were `BusinessFactory` states**:
 * no provisioning path, no wizard, no admin screen set it. So a real dental
 * practice signing up was classified `pii` and its patients' words went to
 * Anthropic. The gate was real and it protected nobody. Decision 272's shape,
 * for the eleventh time in this codebase.
 *
 * ⚠️ THIS SIGNAL IS THE BACKSTOP, NOT THE PRIMARY ONE, and a green test must not
 * be read as coverage. The primary signal is a person answering a question in
 * COMP-02's wizard — which does not exist, and whose customer-facing wording
 * needs owner sign-off before it can. What a category match catches is the
 * practice Google already typed clearly, which is most of them and not all of
 * them. Under-inclusive, deliberately, and stated so the next person extends it
 * rather than trusting it.
 *
 * ⚠️ THE ASYMMETRY THAT SETS THE LIST'S WIDTH. A false positive costs a tenant
 * AI analysis: their reviews are held for a human instead (decision 421), which
 * is a degraded feature they can have back the moment somebody reclassifies
 * them. A false negative puts patient text on the wire to a vendor we hold no
 * BAA with (`docs/SUBPROCESSOR-INVENTORY.md`), which is not recoverable by
 * editing a row afterwards. So the list over-includes on purpose, and
 * `massage`, `massage_spa` and `wellness_center` are where that shows — they are
 * the common false positives, accepted on that asymmetry.
 *
 * ⚠️ `reclassify()` SHIPS WITH ITS CALLER, WHICH IS THE ONLY REASON IT SHIPS AT
 * ALL. The previous phase left it out and said so: a method whose only caller
 * would be a screen that does not exist is the exact shape this class was
 * written to answer (decision 272, eleven instances). `Admin\PhiTenants` is that
 * screen, and it landed in the same phase as this method.
 */
final class TenantClassification
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly BaaRecords $baaRecords,
        private readonly DerivedPurge $derivedPurge,
    ) {}

    /**
     * The Google Places types that mean a HIPAA covered entity, more often than
     * not.
     *
     * ⚠️ VERIFIED AGAINST GOOGLE'S PLACES API (NEW) "PLACE TYPES" PAGE, whose own
     * last-updated date reads **2026-07-28**; fetched **2026-08-04**. Do not edit
     * this list, and do not "modernise" it — every entry below is a live type id
     * on that page, and the notes that follow are the mistakes a rewrite from
     * memory makes.
     *
     * ⚠️ `dentist` AND `dental_clinic` BOTH EXIST IN THE NEW API. It did not
     * rename the legacy id; it added the clinic variants alongside it. Deleting
     * `dentist` as legacy loses every practice Google still types that way.
     *
     * ⚠️ `health` IS THE TABLE B CATCH-ALL AND CARRIES MOST OF THIS LIST'S
     * RECALL. Google has **no type at all** for psychologist, psychiatrist,
     * therapist, optometrist, orthodontist, podiatrist, urgent care, hospice or
     * nursing home — every one a HIPAA covered entity. They fall back to
     * `doctor`, `medical_clinic`, or only `health`. Removing `health` as "too
     * broad" silently drops that entire population.
     *
     * ⚠️ `foot_care` SITS UNDER SERVICES, NOT HEALTH AND WELLNESS — as does
     * `veterinary_care`. A list built by reading one section of that page misses
     * both, which is why the section is not the boundary.
     *
     * ⚠️ `veterinary_care` IS DELIBERATELY EXCLUDED. HIPAA protects the health
     * information of *humans*, so a vet is a false positive by construction under
     * rule 24 — and including it would permanently disable AI analysis for a
     * tenant with no legal reason to lose it. One line to reverse if the owner
     * ever decides this gate answers a broader question than PHI.
     *
     * ⚠️ `spa`, `gym`, `fitness_center`, `beauty_salon`, `tanning_studio` and
     * `child_care_agency` ARE EXCLUDED. Health-adjacent, no clinical record, not
     * covered entities. `spa` is the closest call, and what drains it is that
     * `skin_care_clinic` and `massage_spa` exist as separate types — the
     * medical-spa population Google knows about is typed into those.
     *
     * @var list<string>
     */
    private const array PHI_CATEGORIES = [
        'chiropractor', 'dental_clinic', 'dentist', 'doctor', 'general_hospital',
        'hospital', 'medical_center', 'medical_clinic', 'medical_lab',
        'physiotherapist', 'skin_care_clinic', 'pharmacy', 'drugstore',
        'massage', 'massage_spa', 'wellness_center', 'foot_care', 'health',
    ];

    /**
     * The classification a Google Places category list implies.
     *
     * A pure function: no reads, no writes, no tenant. The caller decides what to
     * do with the answer.
     *
     * ⚠️ THE WHOLE ARRAY IS SCANNED, NEVER `$categories[0]`. Index 0 is Google's
     * `primaryType`, and a primary type is legitimately generic noise —
     * `establishment` or `point_of_interest` — while `dental_clinic` sits further
     * down the list. Verified against the same live docs: the primary type is
     * always **also** duplicated into `types`, so reading position 0 buys nothing
     * and loses every practice whose primary type is generic.
     *
     * ⚠️ AN ABSENT OR EMPTY LIST YIELDS `Pii`, NOT `Phi`. The reasoning lives at
     * the caller in `TenantProvisioner`, because that is where the absence
     * actually happens and where the damage would be.
     *
     * @param  list<string>  $categories
     */
    public function forCategories(array $categories): DataClassification
    {
        return $this->phiCategory($categories) === null
            ? DataClassification::Pii
            : DataClassification::Phi;
    }

    /**
     * The first category in the list that means a covered entity, or null.
     *
     * The same scan as `forCategories()` and deliberately not a second one — it
     * is the one above, with the match returned instead of discarded. Two
     * traversals of one list would be two places for the list to be read
     * differently, which is what the class docblock's "one decider" is about.
     *
     * ⚠️ IT EXISTS SO A RECLASSIFICATION CAN SAY WHY. `reclassify()` writes
     * `$reason` into an append-only compliance log and refuses an empty one,
     * because that entry is the only record of why a tenant's data handling
     * changed. A caller that has just classified a business from Google's own
     * categories can therefore name the category rather than writing prose that
     * restates the outcome — *"Google lists this business as `dental_clinic`"*
     * is answerable later; *"category match"* is not.
     *
     * The returned value is the **normalised** form actually compared, not the
     * caller's spelling, so a stored `Dental_Clinic` is recorded as the
     * `dental_clinic` that matched.
     *
     * @param  list<string>  $categories
     */
    public function phiCategory(array $categories): ?string
    {
        foreach ($categories as $category) {
            // Case-folded and trimmed rather than compared raw. Google returns
            // these lower-cased, but the list also arrives from a stored JSON
            // column, and a repair script or a later importer writing
            // `Dental_Clinic` must not read as "not a dental clinic".
            $normalised = mb_strtolower(trim($category));

            if (in_array($normalised, self::PHI_CATEGORIES, true)) {
                return $normalised;
            }
        }

        return null;
    }

    /**
     * Move a business to another classification, and record who did it and why.
     *
     * ⚠️ RAISING TO `Phi` IS ALLOWED; LOWERING *FROM* `Phi` IS REFUSED, AND THE
     * ASYMMETRY IS THE WHOLE POINT. The two directions do not cost the same
     * thing:
     *
     *   wrongly raised   the tenant's reviews are held for a human instead of
     *                    analysed (decision 421). Degraded, visible, and
     *                    recoverable the moment somebody looks.
     *   wrongly lowered  the tenant's patients' words go on the wire to a vendor
     *                    we hold no BAA with (`docs/SUBPROCESSOR-INVENTORY.md`).
     *                    Not recoverable by editing the row back.
     *
     * There is no wizard question yet and no owner ruling on who may lower a
     * classification or on what evidence, so the safe half ships and the
     * dangerous half refuses. ⚠️ **This is one line to reverse** once that
     * ruling exists — and whoever reverses it owes the ruling, not a judgement
     * call, because the practice that gets lowered by mistake is exactly the one
     * that looked ordinary.
     *
     * ⚠️ RUNS AS THE BUSINESS IT NAMES. `AuditService::record()` opens with
     * `Tenancy::idOrFail()` (decision 419) and `businesses` is FORCE ROW LEVEL
     * SECURITY, so an admin acting on a specific tenant wraps this in
     * `Tenancy::actingAs($business->id, …)`. Filing the entry under whatever
     * tenant the admin was viewing would bury it in the wrong log.
     *
     * ⚠️ THE WRITE AND ITS AUDIT ENTRY ARE ONE TRANSACTION. The entry is the
     * only record of *why* a tenant's data handling changed, and this method
     * refuses to lower — so a column that moved with no entry behind it cannot
     * be walked back through this service at all. `PlaceConfirmation::confirm()`
     * is the precedent, and the wrapper cannot be left to the caller:
     * `TenantProvisioner` happens to run inside registration's transaction
     * (271), and the admin screen has no wrapper of its own.
     *
     * ⚠️ `$reason` IS OPERATOR FREE TEXT AND LANDS IN AN APPEND-ONLY TABLE.
     * Somebody will type *"Dr Jane Smith confirmed by phone"* into it.
     * `BaaRecords::recordExecution()` keeps signer names out of its own metadata
     * for exactly that reason and this is the other half of the same asymmetry:
     * the input is length-bounded at the screen, nothing scrubs it, and a reason
     * nobody can read is not a reason.
     *
     * @param  string  $actor  Who did it — 'user:14', never a bare name.
     *
     * @throws LogicException when the change would lower a PHI tenant
     * @throws InvalidArgumentException when the reason is empty or the business
     *                                  is not the tenant in context
     */
    public function reclassify(
        Business $business,
        DataClassification $to,
        string $actor,
        string $reason,
    ): Business {
        if ((int) $business->id !== Tenancy::idOrFail()) {
            throw new InvalidArgumentException(
                'That business is not the tenant in context. The audit entry for this change '
                .'is filed against the acting business, so it would land in the wrong log. '
                .'Wrap the call in Tenancy::actingAs().'
            );
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(
                'A reclassification needs a reason. This entry is the only record of why a '
                .'tenant\'s data handling changed.'
            );
        }

        $from = $business->data_classification;

        // A CHANGE THAT IS NOT A CHANGE WRITES NOTHING. An append-only
        // compliance log recording moves that did not happen is decision 290's
        // rule at a lower price: `before` and `after` would be identical, and an
        // auditor counting reclassifications would count administrative
        // double-clicks as decisions. The BAA is not opened here either — a
        // business that is already PHI got its record when it became one, by
        // provisioning or by the reclassification that raised it.
        if ($from === $to) {
            return $business;
        }

        if ($from === DataClassification::Phi && $to !== DataClassification::Phi) {
            throw new LogicException(
                'A tenant cannot be moved out of health-information handling here. Lowering is '
                .'the one direction that puts patient text back on the wire to a vendor we hold '
                .'no BAA with, and nobody has ruled on who may do it or on what evidence. '
                .'Raising is available; lowering is not.'
            );
        }

        return DB::transaction(function () use ($business, $to, $from, $actor, $reason): Business {
            // forceFill(), because `data_classification` is Business::$guarded — the
            // guard refuses request-shaped mass assignment and this is the one
            // deliberate writer besides provisioning. See Business::$guarded, which
            // states that the guard and the ArchitectureTest lint are different jobs
            // and neither is the other's backstop.
            $business->forceFill(['data_classification' => $to])->save();

            // ⛔ A RAISE IS RETROSPECTIVE, AND UNTIL THIS LINE NOTHING ACTED ON
            // THAT. Every PHI refusal in this system before it was keyed on what
            // the business was *when the bytes arrived* — the pixel collector
            // (4963a), the L0 archive (4863), the database CHECK beneath them.
            // A business raised here has been running the pixel as `pii`, so the
            // derived warehouse already holds its visitors' rows and every later
            // replay rebuilds them. {@see DerivedPurge} owns the delete and
            // states plainly what it does NOT reach:
            // the L0 objects themselves stay, because there is no KMS key to
            // re-key them under until Stage 3 and a reclassification is not an
            // erasure request. This is the ordinary way a covered entity is
            // identified here, not an edge case — the category match is a
            // backstop and COMP-02's wizard question does not exist.
            //
            // Inside the transaction with the column and the audit entry, on
            // this method's own rule: the entry is the only record of why a
            // tenant's data handling changed, so a purge that happened without
            // one, or an entry describing a purge that rolled back, would both
            // be worse than either alone.
            $purged = $to === DataClassification::Phi
                ? $this->derivedPurge->forBusiness((int) $business->id)
                : ['l1' => 0, 'l2' => 0, 'l3' => 0];

            $this->audit->recordChange(
                'business.reclassified',
                $actor,
                ['data_classification' => $from->value],
                [
                    'data_classification' => $to->value,
                    'reason' => $reason,
                    // ⚠️ THE NUMBERS, BECAUSE "WE CLEARED THE WAREHOUSE" AND "THE
                    // WAREHOUSE WAS ALREADY EMPTY" ARE DIFFERENT FACTS AND AN
                    // AUDITOR WILL WANT THE DIFFERENCE. A zero here is also the
                    // tell that the purge ran and found nothing, rather than
                    // that it never ran.
                    'derived_rows_removed' => $purged,
                ],
                $business,
            );

            // Becoming PHI opens the agreement rule 24 hangs on, so that "until the
            // BAA is executed" has a row to be answered from. Idempotent: a business
            // provisioned as PHI already has one, and a second call returns it.
            if ($to === DataClassification::Phi) {
                $this->baaRecords->open($business);
            }

            return $business;
        });
    }
}
