<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\BusinessFactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A canonical answer the bot may state as fact (DATA-MODEL §5.9).
 *
 * verified_by_owner separates 'inferred from your site' from 'the owner
 * confirmed it'. An unverified fact is never presented as canonical.
 */
final class BusinessFact extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<BusinessFactFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    public const CREATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['verified_by_owner' => 'boolean'];
    }
}
