<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\ActuationTier;
use App\Enums\SiteChangeActor;
use App\Enums\SiteChangeVerdict;
use App\Services\Actuation\SiteChanges;
use Database\Factories\SiteChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One change this platform made to a website it does not own.
 *
 * ⛔ **ONLY `App\Services\Actuation\SiteChanges` MAY TOUCH THIS MODEL**, enforced
 * by an `Architecture\ActuationTest` lint on `CreditLedgerEntry`'s,
 * `MessageCostEntry`'s, `VoiceUsageEvent`'s and `GbpGrantRevocationAttempt`'s
 * precedent (625, 4904, 5071). The reason here is narrower than any of theirs
 * and larger in consequence: **`29` §2 rule 32 — every site change snapshots its
 * prior state and is reversible — is enforced by the writer**, in
 * {@see SiteChanges::open()}, which refuses a change set
 * with an empty `before_snapshot`. A second writer is a second place that
 * refusal can be skipped, and the refusal is the whole of the promise that a
 * stranger's page can be put back.
 *
 * ⚠️ **A ROW HERE IS NOT PROOF THE SITE WAS CHANGED.** `applied_at` is, and it
 * is null until an adapter has actually written. A change set opened against an
 * adapter that then failed is an ordinary condition — somebody else's website
 * was unreachable — and reading `created_at` as the moment of the edit would
 * report an edit that never happened.
 *
 * ⚠️ **`rolled_back_by` IS A KIND OF DECISION, NOT A PERSON** — see
 * {@see SiteChangeActor}. Which human it was lives in the append-only audit row.
 *
 * ⚠️ **`written_page_ref` IS THE ADAPTER'S OWN NAME FOR THE PAGE IT WROTE, AND
 * `url` IS THE ADDRESS WE ASKED FOR** (5966, 6141). They are two different facts
 * and the second is the one that goes stale: an owner may rename a slug or change
 * their permalink structure at any point in the thirty days between the write and
 * the revert, at which point `url` resolves to nothing and the page is still
 * live. **Nothing outside the adapter may interpret this value** — it is stored,
 * carried back inside a `ChangeSet`, and read by the adapter that wrote it.
 *
 * ⚠️ **`undo_requested_at` IS NOT A ROLLBACK AND MUST NEVER BE READ AS ONE**
 * (5835). It means *the owner pressed Undo and nobody has answered them yet* —
 * a T1 undo is queued, because it is up to four requests to a customer's own
 * WordPress. A row carrying it is still live on the site.
 *
 * ⚠️ **`measured_at` IS WHEN THE QUESTION WAS ANSWERED, NOT WHEN THE WINDOW
 * CLOSED.** Both windows travel inside the metric documents, anchored on
 * `applied_at`, which is what makes a measurement deferred by a paused tenant or
 * an unsettled Search Console window produce the identical verdict a week
 * later.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $location_id
 * @property string $url
 * @property string $change_type
 * @property ActuationTier $tier
 * @property array<string, mixed> $before_snapshot
 * @property array<string, mixed> $after_snapshot
 * @property array<string, string> $withheld_fields
 * @property SiteChangeActor $applied_by
 * @property ?Carbon $applied_at
 * @property SiteChangeVerdict $verdict
 * @property ?string $written_page_ref
 * @property ?Carbon $undo_requested_at
 * @property ?Carbon $rolled_back_at
 * @property ?SiteChangeActor $rolled_back_by
 * @property ?string $rolled_back_reason
 * @property ?array<string, mixed> $baseline_metrics
 * @property ?array<string, mixed> $measured_metrics
 * @property ?Carbon $measured_at
 * @property int $revert_attempts
 * @property ?Carbon $revert_attempt_after
 * @property ?Carbon $revert_attempts_exhausted_at
 * @property-read Location $location
 */
final class SiteChange extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<SiteChangeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Whether the adapter reported writing this to the site.
     */
    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }

    /**
     * Whether this change is still on the site as far as we know.
     */
    public function isLive(): bool
    {
        return $this->isApplied() && $this->rolled_back_at === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tier' => ActuationTier::class,
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'withheld_fields' => 'array',
            'applied_by' => SiteChangeActor::class,
            'applied_at' => 'datetime',
            'verdict' => SiteChangeVerdict::class,

            // ⚠️ **A REQUEST, NEVER AN OUTCOME** (5835). It says the owner has
            // asked and nobody has answered yet; `rolled_back_at` is the answer,
            // and `SiteChanges` clears this on every path that reaches one.
            'undo_requested_at' => 'datetime',
            'rolled_back_at' => 'datetime',
            'rolled_back_by' => SiteChangeActor::class,

            // ⚠️ **SLICE H's THREE, AND THEY ARRIVE WITH THEIR WRITER** (5525).
            // `SiteMeasurements` is the only thing that fills them; a CHECK
            // makes all three all-or-nothing, so a row cannot claim it was
            // judged with nothing behind the claim.
            'baseline_metrics' => 'array',
            'measured_metrics' => 'array',
            'measured_at' => 'datetime',

            // ⚠️ **6265's THREE, AND THEY ARRIVE WITH THEIR WRITER TOO.** Only
            // `SiteMeasurements` fills them, and what they bound is the nightly
            // revert retry: how many times we have asked, the earliest we may
            // ask again, and when we stopped.
            'revert_attempt_after' => 'datetime',
            'revert_attempts_exhausted_at' => 'datetime',
        ];
    }
}
