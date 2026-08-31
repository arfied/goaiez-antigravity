<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OptOutScope;
use App\Enums\OutreachChannel;
use App\Enums\SuppressionReason;
use Database\Factories\OptOutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One person, one channel, one refusal — and how far it reaches.
 *
 * NOT TENANT-OWNED, on the `TenancyTest` allowlist with its reason there.
 * A platform-scoped row has no `business_id`, and a global scope would hide
 * precisely the rows the boundary depends on from the one query whose job is to
 * refuse a send.
 *
 * ⚠️ APPEND-ONLY, the same as `ConsentRecord` and for a stronger reason. This is
 * the record produced when somebody said STOP. A row that can be edited or
 * deleted is not evidence, and the edit that matters is the one nobody would
 * notice: deleting a row here does not "resubscribe" anyone, it makes the
 * platform forget it was ever told to stop.
 *
 * ⚠️ A REFUSAL IS RELEASED BY A `SuppressionLift`, NEVER BY A NEW CONSENT
 * RECORD — AND THIS DOCBLOCK SAID THE OPPOSITE UNTIL 1020. It read *"consent is
 * re-granted by a new consent record, never by removing a refusal"*, which was
 * false the whole time and false in the most expensive direction: it named a
 * remedy. `ConsentService::decide()` checks suppression at step 2 and the
 * consent record at step 4, deliberately, so a later record never reaches the
 * question — somebody following this sentence would have written a second
 * consent record, watched it change nothing, and gone looking for the bug in
 * the wrong file. Decision 384's shape in the chokepoint every send passes.
 *
 * ⚠️ A LIFT DOES NOT REMOVE THIS ROW EITHER. The refusal and its reversal are
 * two rows in two append-only tables, matched on `lift_generation`, so STOP →
 * START → STOP is three permanent facts rather than a column somebody flipped
 * twice. See `ConsentService::lift()`.
 *
 * @property-read int $id
 * @property OptOutScope $scope
 * @property ?int $business_id
 * @property OutreachChannel $identifier_type
 * @property string $value_hash
 * @property SuppressionReason $reason_class
 * @property int $lift_generation
 */
final class OptOut extends Model
{
    /** @use HasFactory<OptOutFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'opt_outs is append-only. A refusal is a fact with a date; consent is '
                .'re-granted with a new consent record, never by editing this one.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'opt_outs is append-only. Deleting a row does not resubscribe anybody — '
                .'it makes the platform forget it was told to stop.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => OptOutScope::class,
            'identifier_type' => OutreachChannel::class,
            'reason_class' => SuppressionReason::class,
            'lift_generation' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
