<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Consent\IdentifierHashEpochs;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One key that this install's stored identifier hashes were written under.
 *
 * NOT TENANT-OWNED, on the `Architecture/TenancyTest` scope allowlist with its
 * reason there. There is one application key for the whole deployment, and the
 * question this row answers — *can the suppression registers still refuse
 * anybody?* — is asked on paths where no tenant is resolved at all, a carrier
 * STOP webhook first among them.
 *
 * ⛔ **`retired_at` IS THE MOST CONSEQUENTIAL COLUMN IN THIS SCHEMA AND IT IS
 * WORTH KNOWING WHY BEFORE WRITING IT.** Retiring an epoch is an operator
 * saying: *every hash written under that key is permanently unmatchable, and I
 * accept that every STOP recorded against the shared Lane A number stops
 * refusing anybody.* It is not a cleanup. It is
 * {@see IdentifierHashEpochs::retire()}, it is reachable only through
 * `consent:hash-epoch --accept-loss`, and it takes an actor and a reason because
 * the row is the only record that the decision was ever made.
 *
 * ⚠️ **THE FINGERPRINT IS FROZEN, THE RETIREMENT IS THE ONLY MUTATION.**
 * `ComplianceSuppression`'s rule, for the same reason: editing the fingerprint
 * would make the row a different fact wearing the same id, and this is the fact
 * every send decision on the platform is compared against.
 *
 * ⚠️ **AND THE FREEZE IS A MODEL EVENT, SO IT SEES A SAVED INSTANCE AND NOT A
 * MASS `Builder::update()`** — `ComplianceSuppression` has the same limit and
 * neither claims otherwise. What covers the gap is the chokepoint in
 * `Architecture/ConsentTest`: `IdentifierHashEpochs` is the only file in `app/`
 * allowed to name this model or its table, and the one mass update it performs
 * touches the retirement columns alone. **The CHECK constraint is the layer that
 * holds whatever reaches the database by another route**, and it is why the
 * attribution is enforced there rather than only here.
 *
 * ⚠️ **ONE ROW IS ONE ERA OF A KEY, NOT ONE KEY** (8184). `fingerprint` is
 * indexed rather than unique and a partial unique index allows exactly one
 * **live** row per fingerprint, because an install can legitimately return to a
 * key it has retired — restore a backed-up `.env` and the hashes written under
 * it are readable again. Returning is an append, so the retirement it supersedes
 * survives as its own row rather than being nulled out of existence.
 *
 * @property-read int $id
 * @property string $fingerprint
 * @property CarbonImmutable $first_seen_at
 * @property string $first_seen_by
 * @property ?string $adopted_by
 * @property ?string $adopted_reason
 * @property ?CarbonImmutable $retired_at
 * @property ?string $retired_by
 * @property ?string $retired_reason
 */
final class IdentifierHashEpoch extends Model
{
    public const null CREATED_AT = null;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * The columns an update may touch — the retirement, and nothing else.
     *
     * @var list<string>
     */
    private const array MUTABLE = [
        'retired_at',
        'retired_by',
        'retired_reason',
    ];

    protected static function booted(): void
    {
        self::updating(function (self $epoch): void {
            $frozen = array_diff(array_keys($epoch->getDirty()), self::MUTABLE);

            if ($frozen !== []) {
                throw new LogicException(
                    'An identifier hash epoch is identified by its fingerprint and that is frozen. '
                    .'Changing '.implode(', ', $frozen).' would make this row describe a key it was '
                    .'never observed under, and every send decision on the platform is compared '
                    .'against it.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'immutable_datetime',
            'retired_at' => 'immutable_datetime',
        ];
    }
}
