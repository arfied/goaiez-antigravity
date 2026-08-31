<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\ConnectionStatus;
use App\Enums\OauthProvider;
use Database\Factories\OauthConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A connected external account (DATA-MODEL §5.2).
 *
 * Token columns hold ciphertext, encrypted at the application layer before
 * insert (FOUND-03). They are $hidden so no serialization path — API resource,
 * Livewire payload, log context — can carry them out by accident; the vault
 * service is the only reader.
 *
 * Deliberately NOT an `encrypted` cast. A cast would decrypt on every attribute
 * read, so the model would hold plaintext the moment anything touched it. With
 * explicit encryption in TokenService these properties are ciphertext
 * everywhere, and $hidden is the second layer rather than the only one.
 *
 * @property-read int $id
 * @property OauthProvider $provider
 * @property ConnectionStatus $status
 * @property ?string $external_account_id
 * @property ?string $display_label
 * @property ?string $access_token_enc
 * @property ?string $refresh_token_enc
 * @property ?Carbon $token_expires_at
 * @property ?Carbon $last_refreshed_at
 * @property ?list<string> $scopes
 * @property ?string $last_error
 */
final class OauthConnection extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<OauthConnectionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @var list<string>
     */
    protected $hidden = ['access_token_enc', 'refresh_token_enc'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => OauthProvider::class,
            'status' => ConnectionStatus::class,
            'scopes' => 'array',
            'token_expires_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
        ];
    }
}
