<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property int $business_id
 * @property int $user_id
 * @property string $role
 * @property Carbon $invited_at
 * @property ?Carbon $accepted_at
 * @property ?Carbon $revoked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class BusinessMembership extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
