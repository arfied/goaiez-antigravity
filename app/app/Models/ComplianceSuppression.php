<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ComplianceList;
use App\Enums\OutreachChannel;
use Database\Factories\ComplianceSuppressionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One entry in one external scrubbing register (`29` §2 rule 11).
 *
 * NOT TENANT-OWNED, on the `TenancyTest` scope allowlist with its reason
 * there. These are facts about a phone number rather than about a business, and
 * a global scope would hide precisely the rows the refusal depends on.
 *
 * ⚠️ REMOVABLE, WHICH IS THE OPPOSITE OF `OptOut` AND DELIBERATE. An opt-out is
 * evidence that a person said STOP, so forgetting one makes the platform forget
 * it was told to stop. A register entry is a *copy of somebody else's list*, and
 * numbers genuinely come off the DNC registry — a copy that can only grow drifts
 * further from the source every month and eventually blocks people entitled to
 * be contacted.
 *
 * ⚠️ BUT THE REMOVAL IS SOFT, BECAUSE THE ROW IS THE AUDIT RECORD. `audit_log`
 * is tenant-owned with RLS on `business_id` and these registers belong to no
 * tenant, so a hard delete would leave the direction that *unblocks* somebody
 * with no trace anywhere. `removed_at`, `removed_by` and `removed_reason` are
 * required together by a CHECK constraint.
 *
 * @property-read int $id
 * @property ComplianceList $list
 * @property OutreachChannel $identifier_type
 * @property string $value_hash
 * @property ?string $state
 * @property ?Carbon $effective_from
 * @property ?string $source_reference
 * @property ?Carbon $created_at
 * @property ?Carbon $removed_at
 * @property ?string $removed_by
 * @property ?string $removed_reason
 */
final class ComplianceSuppression extends Model
{
    /** @use HasFactory<ComplianceSuppressionFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * The columns an update may touch.
     *
     * ⚠️ THE IDENTITY OF A ROW IS `(list, identifier_type, value_hash)` AND IT
     * IS FROZEN. Editing any of the three makes the row a different fact wearing
     * the same id — a litigator entry silently becoming a DNC entry changes
     * which purposes it blocks, and nothing downstream would know the row had
     * ever meant something else.
     *
     * What an update *is* for is the two lifecycle transitions the registry
     * performs: a soft removal, and a reload restoring a previously-removed
     * entry. Both rewrite only the columns below, and both go through
     * `SuppressionRegistry`.
     *
     * @var list<string>
     */
    private const array MUTABLE = [
        'removed_at',
        'removed_by',
        'removed_reason',
        'state',
        'effective_from',
        'source_reference',
        'created_at',
        'updated_at',
    ];

    protected static function booted(): void
    {
        self::updating(function (self $entry): void {
            $frozen = array_diff(array_keys($entry->getDirty()), self::MUTABLE);

            if ($frozen !== []) {
                throw new LogicException(
                    'compliance_suppressions identity is (register, channel, identifier) and is '
                    .'frozen. Changing '.implode(', ', $frozen).' makes this a different fact '
                    .'wearing the same id. Remove the entry through SuppressionRegistry and load '
                    .'the corrected one.'
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
            'list' => ComplianceList::class,
            'identifier_type' => OutreachChannel::class,
            'effective_from' => 'immutable_date',
            'created_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
        ];
    }
}
