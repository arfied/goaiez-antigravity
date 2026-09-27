<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\IsTenantRoot;
use App\Contracts\TenantScoped;
use App\Enums\DataClassification;
use App\Enums\IndustryFamily;
use App\Services\Activity\OwnerDigest;
use App\Support\Tenancy;
use Carbon\CarbonInterface;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * The tenant root. Every other tenant-owned model hangs off this one.
 *
 * Uses IsTenantRoot rather than BelongsToTenant: it is scoped on its own primary
 * key, and it cannot fill that key on create because a business is created
 * during signup, before any tenant exists.
 *
 * ⚠️ `$data_classification` IS ANNOTATED BECAUSE STATIC ANALYSIS COULD NOT SEE
 * THE CAST, AND THE DISAGREEMENT WAS DANGEROUS RATHER THAN COSMETIC. `casts()`
 * returns `DataClassification::class` for it and the enum arrives at runtime,
 * but Larastan resolved the property from the column instead and reported that
 * `$business->data_classification === DataClassification::Phi` "will always
 * evaluate to false". Taking that at face value — comparing against
 * `->value` to satisfy it — would have made the comparison false at *runtime*
 * and silently disabled the PHI gate (decision 421), which is the one boundary
 * in this file with a legal cost. The annotation states what the cast already
 * does, so both agree.
 *
 * ⚠️ `$suspended_at` IS ANNOTATED FOR THE SAME REASON AND WITH THE SAME
 * CAUTION. `casts()` returns `datetime` for it and a Carbon arrives at runtime,
 * but Larastan resolves the property from the column and reported that
 * `TenantSuspension::suspendedAt()` "never returns CarbonInterface". Taking
 * that at face value — narrowing the accessor to a string — would have made the
 * status page print a raw timestamp instead of "two days ago", which is the
 * cosmetic end of the same disagreement `$data_classification` records at the
 * dangerous end. The annotation states what the cast already does, so both
 * agree. `$paused_at` is deliberately **not** annotated: nothing reads it as a
 * date through this model, and an annotation with no reader is a claim nobody
 * checks. `$owner_digest_sent_at` is annotated on the same terms as
 * `$suspended_at` — {@see OwnerDigest::eligible()}
 * calls `->lte()` on it, and without the annotation Larastan resolves the
 * property from the column and reports the call as being made on a string.
 *
 * @property-read int $id
 * @property DataClassification $data_classification
 * @property ?CarbonInterface $suspended_at
 * @property ?CarbonInterface $owner_digest_sent_at
 * @property ?CarbonInterface $owner_monthly_digest_sent_at
 */
final class Business extends Model implements TenantScoped
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    use IsTenantRoot;

    /**
     * Guarded rather than fillable: this table has twelve assignable columns
     * today and DATA-MODEL adds more, so an allowlist here would be a list
     * somebody forgets to extend.
     *
     * `id` is assignable here, unlike on every other model, because provision()
     * must set it — the tenant key has to exist before the row does. That is
     * safe only because row-level security refuses any id but the established
     * tenant's: an attacker who mass-assigns `id` gets a policy violation, not
     * another tenant's row.
     *
     * ⚠️ `data_classification` IS THE ONE EXCEPTION, and it was mass-assignable
     * by any `fill()` or `update()` until this line. It decides whether a
     * tenant's customer text may reach a third-party model at all (decisions
     * 421–423) and whether `29` §2 rule 24's separate schema, role and KMS key
     * apply — so a request body that happens to carry the key must not be able
     * to move a dental practice to `pii`.
     *
     * ⚠️ THIS DOES NOT PROTECT `provision()`, AND SAYING OTHERWISE WOULD BE
     * 314–316's mistake. provision() writes through forceFill(), which bypasses
     * every guard by design — that is how the tenant key gets set at all. What
     * the guard stops is *request-shaped* mass assignment; what stops a second
     * decider from appearing is the ArchitectureTest lint that names every file
     * in `app/` allowed to mention this column. The two are different jobs and
     * neither is the other's backstop. Behind both sits
     * `businesses_data_classification_valid`, which constrains the *value* and
     * has nothing to say about who wrote it.
     *
     * @var list<string>
     */
    protected $guarded = [
        'data_classification',
        'paused_at',
        'paused_by',
        'pause_reason',
        // `28` §9.5's Suspend. Guarded for the pause's reason and one more: a
        // suspension is applied *to* this tenant rather than *by* them, and
        // `tenant_isolation`'s WITH CHECK admits any write by the tenant in
        // context — so row-level security is not what stops an owner clearing
        // their own. That is `TenantSuspension`, the gate above it, and this
        // line, and the lint that names the two files allowed to mention these
        // columns at all.
        'suspended_at',
        'suspended_by',
        'suspension_reason',

        // A cursor for `App\Console\Commands\SendOwnerWeeklyDigests`, never a
        // preference — see the creating migration. No owner-facing screen
        // should ever be able to move the date this platform thinks it last
        // told them what it did.
        'owner_digest_sent_at',
        'owner_monthly_digest_sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'address' => 'array',
            'data_classification' => DataClassification::class,
            'industry' => IndustryFamily::class,
            'marketing_sends_enabled' => 'boolean',
            'advanced_dashboard_enabled' => 'boolean',
            'paused_at' => 'datetime',
            'suspended_at' => 'datetime',
            'owner_digest_sent_at' => 'datetime',
            'owner_monthly_digest_sent_at' => 'datetime',
        ];
    }

    /**
     * Create a business and establish it as the tenant.
     *
     * The only supported way to create one. A business cannot be inserted the
     * ordinary way, because its row-level security policy is keyed on its own
     * id: with no tenant established, WITH CHECK cannot match an id that does
     * not exist yet, and USING cannot make the new row visible to the RETURNING
     * clause Laravel uses to read the id back.
     *
     * Taking the id from the sequence first inverts that. The tenant is known
     * before the row exists, so both halves of the policy are satisfied by an
     * ordinary insert and the table needs no signup exception.
     *
     * Leaves the tenant established, which is what signup wants — the caller has
     * just become this business. Call Tenancy::forget() if that is not intended.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function provision(array $attributes): self
    {
        $id = self::allocateId();

        Tenancy::set($id);

        $business = new self;
        $business->forceFill([...$attributes, 'id' => $id])->save();

        return $business;
    }

    /**
     * Reserve the next business id without inserting anything.
     *
     * nextval() is not transactional — a rolled-back provision burns the id
     * rather than reusing it. That is the correct trade: gaps in a surrogate key
     * are harmless, and the alternative is two sessions racing for the same id.
     */
    public static function allocateId(): int
    {
        $row = DB::connection('pgsql')->selectOne(
            "SELECT nextval(pg_get_serial_sequence('businesses', 'id')) AS id"
        );

        return (int) $row->id;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function citations(): HasMany
    {
        return $this->hasMany(Citation::class);
    }

    public function hasAdvancedDashboard(): bool
    {
        return (bool) ($this->advanced_dashboard_enabled ?? false);
    }

    public function enableAdvancedDashboard(): void
    {
        $this->update(['advanced_dashboard_enabled' => true]);
    }

    public function disableAdvancedDashboard(): void
    {
        $this->update(['advanced_dashboard_enabled' => false]);
    }
}
