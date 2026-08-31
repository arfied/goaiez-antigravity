<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\InboundMediaOutcome;
use Database\Factories\InboundMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One picture a customer texted this business, or the record that we refused to
 * keep one — T176 P10.
 *
 * ⚠️ **THE ROW IS THE OUTCOME, NEVER THE INTENT.** It is written once, after the
 * decision is final: either the bytes are on the disk it names, or the outcome
 * is a refusal and every storage column is null. There is no `pending` state,
 * because a pending row that a lost job never revisits is a picture the owner is
 * told exists and can never open — and the migration's CHECK is what makes the
 * two shapes the only two.
 *
 * ⚠️ **`created_at` ONLY, AND UPDATES ARE REFUSED — WITH EXACTLY ONE
 * EXCEPTION.** {@see CampaignReply} sets `UPDATED_AT` to null for the same
 * reason and this goes one step further: a `stored` row rewritten to a refusal,
 * or the reverse, would rewrite what we claim happened to a member of the
 * public's photograph. A capture that has to be redone deletes and re-runs; it
 * does not edit. ⛔ **The exception is the retention prune** —
 * `stored → pruned` with every storage column nulled in the same save, and
 * nothing else — because that adds a fact rather than revising one. See
 * {@see self::isBeingPruned()}, which holds the argument and the narrowing.
 *
 * ⚠️ **DELETING IS DELIBERATELY ALLOWED**, which is where this differs from
 * {@see InboundMessage}. That table is append-only because deleting a row
 * re-arms a webhook; nothing here is a replay defence beyond the unique index,
 * and erasure has to be able to reach a customer's photograph. ⚠️ **"What is
 * owed and is not built is the object" (4172) IS NARROWER THAN IT WAS**: a
 * *deleted row* still leaves its bytes on the object store, and that gap is
 * open — but the *retention* path deletes the object first and only then
 * rewrites the row, so bytes reached by `storage:prune` genuinely go
 * ({@see StorageRetention}). What 4172 named is the erasure path, and it is
 * still owed.
 *
 * ⚠️ **NOTHING HERE IS A URL.** See the creating migration: the media address is
 * used once by the fetcher and never persisted.
 *
 * @property int $inbound_message_id
 * @property int $ordinal
 * @property InboundMediaOutcome $outcome
 * @property ?string $content_type
 * @property ?int $byte_size
 * @property ?string $checksum
 * @property ?string $storage_disk
 * @property ?string $storage_path
 * @property ?Carbon $created_at
 */
final class InboundMedia extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<InboundMediaFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * ⚠️ **THE TABLE NAME IS NOT WHAT THE INFLECTOR WOULD GUESS.**
     * `InboundMedia` pluralises to `inbound_medias`, and `media` is already a
     * plural. Stated rather than left to the inflector, because the failure is a
     * missing relation at runtime rather than an error at boot.
     */
    protected $table = 'inbound_media';

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    protected static function booted(): void
    {
        self::updating(function (InboundMedia $media): void {
            if ($media->isBeingPruned()) {
                return;
            }

            throw new LogicException(
                'inbound_media rows are not revised. A row records what happened to a '
                .'photograph somebody sent this business — rewriting a refusal into a '
                .'stored row, or the reverse, changes what we claim we did with it. '
                .'Delete and re-capture instead. The one permitted update is the '
                .'retention prune: stored → pruned, with every storage column nulled '
                .'in the same save (StorageRetention, decision 4944).'
            );
        });
    }

    /**
     * Whether this save is the retention prune, and nothing else — decision
     * 4944.
     *
     * ⛔ **THE ONE PERMITTED UPDATE ON AN OTHERWISE APPEND-ONLY TABLE, AND IT IS
     * NARROWED TO A SINGLE TRANSITION RATHER THAN RELAXED.** The guard above
     * exists because rewriting a refusal into a stored row, or the reverse,
     * changes what we claim we did with a member of the public's photograph.
     * {@see StorageRetention} needs neither of those. It needs to record a *new*
     * fact — we held it, the keeping period ran out, we deleted it — which
     * `InboundMediaOutcome::Pruned` is a case for precisely so that the claim is
     * added rather than revised. `Stored` survives in the meaning of `Pruned`:
     * pruned means *stored, then deleted*, and no other origin can reach it.
     *
     * ⛔ **THE ALTERNATIVE WAS DELETING THE ROW, WHICH THE GUARD'S OWN MESSAGE
     * SUGGESTS, AND IT IS WORSE BY THE GUARD'S OWN LOGIC.** `InboundMediaOutcome`
     * opens by saying that a refusal writing nothing is indistinguishable from an
     * MMS that carried no media at all. Deleting a pruned row makes a photograph
     * we kept for months and then deleted indistinguishable from one that was
     * never sent — the same loss, one step later, and it takes the record away
     * from the only person it protects.
     *
     * ⚠️ **THE CHECK IS ON THE WHOLE DIRTY SET AND NOT ON THE OUTCOME ALONE.** A
     * save that moved `outcome` to `Pruned` while leaving `storage_path` set, or
     * that changed `ordinal` or `inbound_message_id` on the way past, is refused
     * exactly as before — so this is not an escape hatch that a future caller can
     * widen by writing one extra column. The creating migration's CHECK enforces
     * the resulting shape underneath, which is decision 216's second layer doing
     * its job rather than being moved out of the way.
     */
    private function isBeingPruned(): bool
    {
        $changes = $this->getDirty();

        $permitted = [
            'outcome' => InboundMediaOutcome::Pruned->value,
            'storage_path' => null,
            'storage_disk' => null,
            'content_type' => null,
            'byte_size' => null,
            'checksum' => null,
        ];

        // Every permitted column, all of them, and nothing else. `ksort` on both
        // sides because the order a caller assembles the array in is not part of
        // the claim.
        ksort($changes);
        ksort($permitted);

        return $changes === $permitted
            && $this->getRawOriginal('outcome') === InboundMediaOutcome::Stored->value;
    }

    /**
     * @return BelongsTo<InboundMessage, $this>
     */
    public function inboundMessage(): BelongsTo
    {
        return $this->belongsTo(InboundMessage::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => InboundMediaOutcome::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
