<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\DataClassification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One conformed event — `GOAIEZ_PIXEL_MASTER_BUILD` §5.3.
 *
 * ⚠️ **DERIVED AND DISPOSABLE.** §5.1: *"L1 and L2 are derived and disposable —
 * you must be able to `TRUNCATE` and rebuild them from L0 without data loss."*
 * Nothing may be written here that is not a function of an L0 object, because a
 * value with no L0 source is a value the next replay destroys. That includes an
 * innocent-looking `updated_at`, a moderation flag, or a backfilled correction:
 * corrections belong in L0 as a new object, which is the whole reason the
 * landing layer is append-only rather than editable.
 *
 * ⛔ **NEVER `save()` ON AN EXISTING ROW.** A mutated L1 row is a row that
 * disagrees with its own `l0_path`, and the disagreement is invisible until
 * somebody replays and the number changes. The writers are
 * [[\App\Services\Warehouse\Replayer]] and
 * [[\App\Jobs\ArchivePixelBatchJob]], both through
 * [[\App\Services\Warehouse\L1Loader]], which inserts and never updates.
 *
 * ⚠️ **A COMPOSITE PRIMARY KEY, WHICH ELOQUENT DOES NOT MODEL** — the same
 * shape as [[L2FactSourceDaily]] and the five marts beside it, and this model
 * joined them on 2026-08-20 (6182, 6240). The key is
 * `(business_id, event_id)`; `$primaryKey` is set to `business_id` only so
 * Eloquent has something to answer with, **it is not unique and must not be
 * used to fetch a row**, and `find()` on this model is meaningless.
 *
 * ⛔ **THIS DOCBLOCK SAID "`$incrementing` IS FALSE AND THE KEY IS THE
 * `event_id` L0 ASSIGNED" AND THE SECOND HALF WAS A CROSS-TENANT DEFECT RATHER
 * THAN A DESCRIPTION — BOTH READINGS KEPT AND DATED.** `event_id` is minted in
 * the browser and was a **global** key, so the second tenant to present an id
 * already held by the first lost the event to `ON CONFLICT DO NOTHING` — a
 * unique index is not filtered by row-level security. ⚠️ **`$incrementing` is
 * still false and always was**; what changed is which columns the key is.
 *
 * ⛔ **"NO PRODUCTION WRITER EXISTS ABOVE IT — THE COLLECTOR IS UNBUILT" WAS
 * TRUE AND HAS BEEN FALSE SINCE 2026-08-18 — CORRECTED 2026-08-22 (8022).**
 * It continued *"so nothing puts a byte in L0 and nothing therefore lands here
 * outside a test. That is decision 272's shape … read 4861 before building a
 * report on this table."* **The collector was built at 4960–4979** and
 * `ArchivePixelBatchJob` writes L0 **and this table** in the same job, inline.
 * ⛔ **THE COST OF LEAVING THIS WAS PAID ON 2026-08-22**: a lane was sent to
 * build exactly the report this paragraph forbids, and the paragraph would have
 * told it not to. **A docblock that instructs the next agent not to build
 * something is the most expensive form 314–316 takes**, because obeying it
 * leaves no trace.
 *
 * ⚠️ **WHAT SURVIVES, AND IT IS THE HALF WORTH KEEPING**: no production L0
 * object has ever come from anywhere but a test, so on every deployment that
 * exists this table is empty — for a reason about traffic rather than about
 * writers. A reader built on it must say which of the two it means.
 *
 * @property string $event_id
 * @property int $business_id
 * @property DataClassification $data_class
 * @property string $event_type
 * @property string $consent_state
 * @property string|null $anonymous_id
 * @property string|null $session_id
 * @property Carbon $occurred_at
 * @property Carbon $received_at
 * @property bool $is_bot
 * @property int $bot_score
 * @property string $page_path
 * @property string $page_host
 * @property string|null $referrer_host
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string $device_type
 * @property string $properties
 * @property string $l0_path
 * @property int $schema_version
 */
final class L1Event extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * ⚠️ NOT `timestamps()`. There is no `created_at` and no `updated_at` on this
     * table by design — see the migration, rule 2. Leaving this true would have
     * Eloquent write two columns that do not exist, and the first thing anybody
     * would reach for to fix it is adding them.
     */
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'l1_events';

    protected $primaryKey = 'business_id';

    protected $keyType = 'int';

    /**
     * ⚠️ `properties` IS DELIBERATELY NOT CAST TO AN ARRAY. Casting it would have
     * Eloquent re-encode the column through `json_encode()` on every write, with
     * whatever flags the framework picked — which is precisely the byte
     * instability [[\App\Services\Warehouse\CanonicalJson]] exists to remove. The
     * column holds canonical JSON text and a reader that wants a structure calls
     * `CanonicalJson::decode()` on it explicitly.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_class' => DataClassification::class,
            'occurred_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'is_bot' => 'boolean',
            'bot_score' => 'integer',
            'schema_version' => 'integer',
        ];
    }
}
