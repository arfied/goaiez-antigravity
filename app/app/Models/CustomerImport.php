<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A tenant's uploaded customer list, and the attestation that came with it.
 *
 * ⚠️ **NOT A CONSENT RECORD, AND NOT A PERMIT.** Decision 549 approved
 * reactivation messaging on a tenant's EBR attestation; this is where that
 * attestation lives. `ConsentService::permit()` is still the only thing that
 * decides whether anybody may be messaged, and it reads `consent_records`. A row
 * here is evidence about a *claim*, and the whole point of separating them is
 * that the two must never be confused by a later reader looking for "did we have
 * permission".
 *
 * **APPEND-ONLY, ENFORCED RATHER THAN DESCRIBED.** ConsentRecord's reasoning
 * (decision 295) applies with more force, not less: an attestation that can be
 * edited after the fact is not evidence of anything, because the version of the
 * words the tenant actually saw is precisely what a challenge would dispute. The
 * guards below make an UPDATE a `LogicException` rather than a code-review note.
 *
 * @property-read int $id
 * @property int $business_id
 * @property ?int $location_id
 * @property string $statement_version
 * @property Carbon $attested_at
 * @property string $attested_by
 * @property array<string, mixed> $proof
 * @property int $row_count
 * @property string $source
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
final class CustomerImport extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attested_at' => 'datetime',
            'proof' => 'array',
            'row_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'A customer import attestation is append-only. The wording a tenant agreed to '
                .'and the moment they agreed to it are the entire evidentiary value of this row '
                .'(decision 551); an edited attestation proves nothing, whatever it says. Record '
                .'a new import instead.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'A customer import attestation is append-only and cannot be deleted. If a list '
                .'must stop being messaged, that is suppression on the contacts themselves '
                .'(ConsentService::suppress()), not the destruction of the record that they were '
                .'ever claimed.'
            );
        });
    }
}
