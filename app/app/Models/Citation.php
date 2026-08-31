<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A directory citation tracking NAP (Name, Address, Phone) consistency.
 *
 * @property int $id
 * @property int $business_id
 * @property int|null $location_id
 * @property string $directory
 * @property string|null $directory_url
 * @property string $nap_status
 * @property string|null $listing_name
 * @property string|null $listing_address
 * @property string|null $listing_phone
 * @property array|null $mismatch_details
 * @property Carbon|null $last_checked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Citation extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $guarded = [
        'id',
        'business_id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'mismatch_details' => 'array',
            'last_checked_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isConsistent(): bool
    {
        return $this->nap_status === 'consistent';
    }

    public function isMismatch(): bool
    {
        return $this->nap_status === 'mismatch';
    }

    public function isMissing(): bool
    {
        return $this->nap_status === 'missing';
    }
}
