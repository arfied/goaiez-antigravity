<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\OauthProvider;
use Database\Factories\ProviderHealthFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Current integration health, one row per (business, provider)
 * (DATA-MODEL §5.2). Pure current state — updated_at only, no created_at.
 *
 * @property-read int $id
 * @property OauthProvider $provider
 * @property ?string $token_status
 * @property ?string $last_error
 * @property ?Carbon $last_sync_at
 */
final class ProviderHealth extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ProviderHealthFactory> */
    use HasFactory;

    /**
     * DATA-MODEL gives this table no created_at.
     */
    public const null CREATED_AT = null;

    /**
     * Not the conventional plural — DATA-MODEL names the table in the
     * singular, and "health" does not pluralize predictably anyway.
     */
    protected $table = 'provider_health';

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
            'provider' => OauthProvider::class,
            'last_sync_at' => 'datetime',
        ];
    }
}
