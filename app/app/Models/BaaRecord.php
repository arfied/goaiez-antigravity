<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\BaaStatus;
use Carbon\CarbonImmutable;
use Database\Factories\BaaRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One tenant's Business Associate Agreement — `29` §2 rule 24.
 *
 * TENANT-OWNED, unlike `LegalDocument`. The template is our text and belongs to
 * nobody (417–420); an execution is one named business agreeing to one exact
 * version of it, and it carries the names of the people who signed.
 *
 * ⚠️ EVERYTHING EXCEPT THE TIMESTAMPS IS GUARDED, AND THAT IS THE DESIGN RATHER
 * THAN TIDINESS. `status` is what `29` §2 rule 24 reads, and a screen or a
 * request that can mass-assign it can declare an agreement executed with no
 * signature behind it. `BaaRecords` is the only writer — it writes through
 * forceFill(), an ArchitectureTest lint names it as the only file allowed to
 * touch this model, and the `baa_records_executed_has_evidence` CHECK sits under
 * both. Three layers, each catching what the others cannot (216, 314–316).
 *
 * ⚠️ `tenant_signer_name`, `tenant_signer_title` AND `goaiez_signer` ARE PII OF
 * NAMED INDIVIDUALS. Never logged, never in a URL, never in a toast.
 *
 * @property-read int $id
 * @property int $business_id
 * @property ?int $legal_document_id
 * @property BaaStatus $status
 * @property ?CarbonImmutable $tenant_signed_at
 * @property ?string $tenant_signer_name
 * @property ?string $tenant_signer_title
 * @property ?CarbonImmutable $goaiez_signed_at
 * @property ?string $goaiez_signer
 * @property ?CarbonImmutable $revoked_at
 * @property ?string $revoke_reason
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
final class BaaRecord extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<BaaRecordFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'business_id',
        'legal_document_id',
        'status',
        'tenant_signed_at',
        'tenant_signer_name',
        'tenant_signer_title',
        'goaiez_signed_at',
        'goaiez_signer',
        'revoked_at',
        'revoke_reason',
    ];

    /**
     * Whether rule 24's "until the BAA is executed" is satisfied for this row.
     *
     * Delegates to the enum rather than comparing here, so there is one
     * definition of "in force" and a revoked agreement cannot read as live
     * through a `!== Pending` written somewhere else.
     */
    public function isInForce(): bool
    {
        return $this->status->isInForce();
    }

    /**
     * The exact version executed, or null while nothing has been.
     *
     * @return BelongsTo<LegalDocument, $this>
     */
    public function legalDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BaaStatus::class,
            'tenant_signed_at' => 'immutable_datetime',
            'goaiez_signed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
