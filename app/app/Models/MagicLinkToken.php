<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single-use login link.
 *
 * Deliberately NOT tenant-owned, and on the TenancyTest allowlist for the
 * same reason `users` is: this row is written and read *before* anyone is
 * authenticated, so there is no tenant to scope it by. Keyed on email rather
 * than user id so that asking for a link cannot reveal whether an account
 * exists.
 *
 * The row holds a SHA-256 of the token, never the token. A database dump must
 * not be a set of live login links.
 *
 * @property-read int $id
 * @property string $email
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property ?Carbon $consumed_at
 * @property ?string $requested_ip_hash
 */
final class MagicLinkToken extends Model
{
    /**
     * Written once and consumed once; there is no meaningful updated_at.
     */
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * The token itself is never an attribute of this model — it exists only in
     * the email — so there is nothing to hide. The hash is hidden anyway,
     * because a hash is still a lookup key.
     *
     * @var list<string>
     */
    protected $hidden = ['token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture();
    }
}
