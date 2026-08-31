<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LiftSource;
use App\Enums\OptOutScope;
use App\Enums\OutreachChannel;
use Database\Factories\SuppressionLiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One reversal of one refusal — who cleared it, on what authority, and which
 * generation of it they cleared.
 *
 * NOT TENANT-OWNED, on the `TenancyTest` scope allowlist beside `OptOut`
 * and for the identical reason: a platform-scoped row has no `business_id`, and
 * a global scope would hide precisely the rows the send boundary depends on from
 * the one query whose job is to answer whether a refusal still stands.
 *
 * ⚠️ APPEND-ONLY, THE SAME AS `OptOut`, AND THE SYMMETRY IS THE POINT. A lift
 * that can be deleted is a suppression that can be reinstated with no record;
 * a lift that can be edited is one whose actor and authority can be changed
 * after the fact. Between the two tables the sequence STOP → lift → STOP is
 * three immutable rows, and the account of what happened cannot be rewritten by
 * anybody, including us.
 *
 * ⚠️ `generation` IS AN EXACT MATCH, NEVER A RANGE OR A COMPARISON. A lift at
 * generation 0 clears a suppression at generation 0 and nothing else. Written as
 * `>=` it would pre-clear every future STOP for that identifier — a customer who
 * said STOP, was lifted, and said STOP again would be quietly sendable, which is
 * the failure the whole `opt_outs` table exists to prevent.
 *
 * @property-read int $id
 * @property OptOutScope $scope
 * @property ?int $business_id
 * @property OutreachChannel $identifier_type
 * @property string $value_hash
 * @property int $generation
 * @property LiftSource $source
 * @property string $actor
 * @property ?string $note
 */
final class SuppressionLift extends Model
{
    /** @use HasFactory<SuppressionLiftFactory> */
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
                'suppression_lifts is append-only. Who lifted a suppression and on whose '
                .'authority is the entire value of the row; a lift that can be edited after '
                .'the fact records nothing.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'suppression_lifts is append-only. Deleting a lift re-suppresses somebody '
                .'with no record that anybody decided to, which is the mirror of the rule '
                .'opt_outs already refuses.'
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
            'source' => LiftSource::class,
            'generation' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
