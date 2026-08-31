<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\AuditLogEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One entry in the compliance audit log (DATA-MODEL §5.12).
 *
 * APPEND-ONLY (§5.14): an audit trail that can be edited is not an audit
 * trail, so updates and deletes throw at the model layer. Known gap, on
 * purpose: a Query Builder mass update/delete bypasses model events — that
 * path is what code review and laravel-reviewer watch for.
 *
 * ⛔ AND KEPT FOR EVER, BY RULING RATHER THAN BY DEFAULT (8043). No pruner may
 * be written for this table. That is what makes the rule below load-bearing
 * rather than tidy: anything written here is written permanently.
 *
 * Metadata never carries customer personal data or secrets — name the entity
 * by type and id and let the reader look it up under their own authority.
 *
 * ⛔ THAT SENTENCE WAS FALSE FROM STAGE 0 UNTIL 2026-08-22, AND THE ROWS IT WAS
 * FALSE ABOUT ARE STILL HERE — BOTH READINGS KEPT AND DATED (4368, 8044, 8046).
 * `ConsentService` wrote an end customer's phone number or email address in
 * clear into the metadata of `consent.withdrawn`, `consent.lift_refused` and
 * `consent.lifted`, by a written exception that argued a suppression is keyed on
 * the identifier and cannot be investigated without it. The exception was real,
 * it was argued, and it ran for the whole life of the table. **It stopped on
 * 2026-08-22**: those three sites write the entity reference and nothing else.
 *
 * ⛔ AND THE REFERENCE RESOLVES TO TWO DIFFERENT THINGS, WHICH THIS PARAGRAPH
 * CLAIMED IT DID NOT UNTIL A COMPLIANCE REVIEW SAID OTHERWISE (8060). Where the
 * refusal is this tenant's, the entity is a `SuppressionListEntry` and the
 * identifier is read from `suppression_list`, which holds the authoritative copy
 * in clear and cascades on the same `business_id` this table does — one lookup,
 * and it names the person. ⚠️ **Where the refusal lives only in the platform
 * register, the entity is an `OptOut` and there is no clear copy anywhere in
 * this schema**: `opt_outs` stores `Identifier::hash()`, so a reader can
 * **confirm a candidate** identifier and cannot **produce** one. That is a
 * materially different answer to give a regulator, and it is the honest state
 * of the two arms rather than a shortfall in one — the platform register was
 * never allowed a clear copy, by its own design.
 *
 * ⚠️ THE SECOND ARM IS WHY THE FIRST SENTENCE OF THIS BLOCK IS NOT THE WHOLE
 * RULE. A reader who takes *"look it up under their own authority"* to mean
 * *"an address is always one query away"* will be wrong about the arm they are
 * least likely to be looking at, and most likely to be asked about.
 *
 * ⚠️ THE HISTORIC ROWS KEEP IT AND THERE IS NO BACK-FILL. Rewriting them means
 * the Query Builder path the guard above exists to catch, and 8043 forbids it —
 * the owner accepted that consequence knowingly. **So a reader of this table
 * must expect contact details in any row written before 2026-08-22**, and the
 * sentence above is a rule about what is written from now on rather than a
 * description of what is in here.
 *
 * ⚠️ THERE IS NO LINT OVER THE CALL SITES AND THAT IS ALSO A RULING (8046). It
 * was offered and the docblock was chosen instead, so this paragraph is the
 * whole of the enforcement: the next author who wants to put a contact detail in
 * a metadata array is stopped by having read this, or not at all.
 */
final class AuditLogEntry extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<AuditLogEntryFactory> */
    use HasFactory;

    /**
     * Append-only rows have no meaningful updated_at.
     */
    public const null UPDATED_AT = null;

    /**
     * The model is one entry; the table is the log.
     */
    protected $table = 'audit_log';

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'audit_log is append-only (DATA-MODEL §5.14). Write a new row '
                .'instead of editing history.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'audit_log is append-only (DATA-MODEL §5.14). Rows are never '
                .'deleted.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
