<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\MessagingLane;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A tenant's customer (DATA-MODEL §5.5).
 *
 * ⚠️ `deleted_at` IS NOT LARAVEL'S SOFT DELETE, and this model must never adopt
 * the `SoftDeletes` trait — a lint asserts it. It is D-205's third contact
 * state: a tombstone the owner can undo for seven days and nothing ever purges
 * (1540), because `consent_records` cascades on delete and a tenant's proof of
 * consent is not theirs to destroy by tidying a rolodex. Written only by
 * `App\Services\Crm\CustomerEditor`, held there by a second lint.
 *
 * `messaging_lane` is guarded alongside the tenant key and has no setter, and
 * that is a hard rule, not a style choice: the lane is DERIVED from
 * consent_records by ConsentService (§5.6, §5.14). Code that wants to change a
 * lane writes a consent record; nothing assigns the lane from input.
 *
 * `is_suppressed` was removed in row 3 slice A (decision 286). Suppression is
 * per (channel, identifier) and a single boolean could not say "suppressed on
 * SMS, reachable on email". Ask ConsentService::permit().
 *
 * `region_code` is the recipient's jurisdiction — a two-letter USPS code — and
 * it is what `29` §2 rule 11's state mini-TCPA rules key on. ✅ **It has a
 * writer as of row 4 slice 5** (1594): `App\Services\Crm\CustomerEditor::
 * setRegion()` behind the contact profile, and `App\Services\Consent\
 * CustomerImports` for a `state` column on an uploaded list. Those two are the
 * whole allowlist and a lint holds them there. A contact still carrying null is
 * refused every *marketing* send with `SendRefusalReason::StateUnknown`, which
 * is the fail-closed direction and costs nothing while every message this
 * product sends is transactional. ⚠️ **Never infer it** — not from the phone
 * number, not from an address string, not from a line-type database. See the
 * creating migration and `setRegion()`, which argue each one.
 *
 * `merged_into_id` has existed since Stage 0 and had **zero readers and zero
 * writers anywhere in `app/`** until `34` §1.2's merge (1326) — decision 272's
 * shape applied to a column, the same way `display_on_website` (403),
 * `users.role` (740) and `triage_threshold` (1423) were. It is written only by
 * `App\Services\Crm\CustomerMerges`, held there by a lint, and a row carrying it
 * is not a contact any more: it is hidden from every list and every picker, and
 * `ConsentService::decide()` refuses to message it before it looks at an
 * identifier.
 *
 * @property ?string $region_code
 * @property bool $sms_consent
 * @property bool $email_consent
 * @property MessagingLane $messaging_lane
 * @property ?string $consent_source
 * @property ?list<string> $tags
 * @property ?Carbon $archived_at
 * @property ?Carbon $deleted_at
 * @property ?int $merged_into_id
 * @property ?Carbon $first_seen_at
 * @property ?Carbon $last_activity_at
 */
final class Customer extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * §5.5 gives customers created_at only; last_activity_at plays the
     * updated_at role and is set by the code that touches the customer.
     */
    public const null UPDATED_AT = null;

    /**
     * `messaging_lane`, the two consent booleans and `consent_source` are all
     * DERIVED from consent_records and written only by ConsentService. Guarding
     * them is what makes "derived" a property of the language rather than a
     * comment — the same move the lane already had, extended to the three
     * columns that were sitting beside it unguarded.
     *
     * ⚠️ **`region_code` JOINS THEM FOR THE SAME REASON AND NOT THE SAME RULE**
     * (1610). It is not derived — a human answers it — but it is held to one
     * writer by a chokepoint lint, and a lint is a *textual* claim: a variable
     * holding the column name, reflection, a concatenated column list and
     * `Model::unguarded()` are all out of its reach whatever its patterns are.
     * Guarding the column makes the rule a property of the language for exactly
     * those, the way `messaging_lane` already did.
     *
     * ⚠️ **THE TWO LAYERS OVERLAP AND ONE SHAPE FELL THROUGH BOTH** (1621).
     * `$customer['region_code'] = 'FL'` reaches `Model::offsetSet()`, which calls
     * `setAttribute()` **without asking `isFillable()`** — so `$guarded` does not
     * see it, and neither did any of the lint's four arms. It is arm 4 now.
     * ⚠️ **AND THIS PARAGRAPH USED TO DESCRIBE THE ONE-ARM LINT** — "it matches
     * `->region_code =` and cannot see `Customer::create([…])`, `->update([…])`,
     * `fill()` or a hand-built payload" — which has been wrong since 1610 caught
     * every one of those. A reader who believed it would under-trust the lint and
     * over-trust this list; both are needed, and neither is complete alone.
     *
     * ⚠️ **THIS DOES NOT AFFECT FACTORIES.** `Factory::makeInstance()` wraps
     * construction in `Model::unguarded()`, so
     * `Customer::factory()->create(['region_code' => 'FL'])` keeps working and a
     * test that pins the gate rather than the writer still says so directly.
     *
     * ⛔ **`tags` JOINS THEM BECAUSE ITS CHOKEPOINT HAD BEEN CLAIMING IT WAS
     * HERE SINCE THE DAY THAT LINT WAS WRITTEN, AND IT WAS NOT** (9082).
     * CrmTest's "a contact's tags are written in exactly one place" closes its
     * own reasoning with *"…which is why `tags` is also in
     * `Customer::$guarded`"*, and the paragraph above that line argued that
     * **two** of the eight write shapes evade `$guarded`. For this column all
     * eight did: `tags` was mass-assignable, and `Customer` carries no Fillable
     * attribute, so this list is the whole mass-assignment story.
     *
     * ⛔ **AND THAT "TWO OF EIGHT" FIGURE WAS ITSELF WRONG — CORRECTED
     * 2026-08-24 (9140–9143).** It is **six outright and two in part**:
     * `Eloquent\Builder::update()` is
     * `return $this->toBase()->update($this->addUpdatedAtColumn($values));` and
     * **consults nothing**, so `Customer::query()->whereKey($id)->update([…])`
     * writes any column on this model whatever this list says. ⚠️ **So
     * `$guarded` is a thinner layer than every artefact here described**, and
     * the honest reading is that it refuses mass assignment and nothing else —
     * which is a reason the textual chokepoints must carry the full arm set,
     * not a reason to delete this list. That is 314–316 in its most
     * expensive form, because the sibling claim about `region_code` two hundred
     * lines further down is TRUE, which is what made this one read as
     * considered.
     *
     * ⚠️ **THE ARGUMENT IS `region_code`'s, NOT A TIDY-UP.** The column is a
     * `jsonb` LIST and every write replaces the whole set, so a second writer
     * working from a stale copy drops a tag with both diffs looking correct —
     * which is where a textual lint's blind spots cost the most.
     * `CustomerEditor::retag()` assigns the property directly, and a direct
     * assignment is not mass assignment, so the one permitted writer is
     * untouched; nothing in `app/`, `database/`, `routes/` or `tests/`
     * mass-assigns this column outside a factory. **The claim is asserted in
     * CrmTest now rather than written down again** — a comment cannot fail the
     * build when it stops being true, which is the whole of how the last one
     * survived.
     *
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'business_id',
        'messaging_lane',
        'sms_consent',
        'email_consent',
        'consent_source',
        'region_code',
        'tags',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'messaging_lane' => MessagingLane::class,
            'sms_consent' => 'boolean',
            'email_consent' => 'boolean',
            'tags' => 'array',
            'custom_fields' => 'array',
            'score_advocate' => 'integer',
            'score_churn_risk' => 'integer',
            'value_to_date_cents' => 'integer',
            'request_count' => 'integer',
            'archived_at' => 'datetime',
            'deleted_at' => 'datetime',
            'last_requested_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return HasMany<ConsentRecord, $this>
     */
    public function consentRecords(): HasMany
    {
        return $this->hasMany(ConsentRecord::class);
    }
}
