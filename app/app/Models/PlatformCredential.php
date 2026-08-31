<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CredentialEnvironment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One of *our* vendor credentials (doc `38` Part 1, D-149).
 *
 * Ours, not a tenant's — one Google Places key, one Turnstile secret, one
 * Infobip account — so it sits on the `TenancyTest` scope allowlist as
 * platform-wide configuration, alongside `platform_settings` and
 * `plan_entitlements`. A tenant's own OAuth tokens are a different thing and
 * live encrypted per connection behind `TokenService`, which is tenant-owned and
 * stays that way. Nothing here should ever grow a `business_id`.
 *
 * ⚠️ **NOTHING SHOULD REACH THIS MODEL DIRECTLY.** `App\Services\Config\
 * CredentialStore` is its only reader and writer and an `ArchitectureTest` lint
 * holds that, for a sharper reason than the usual chokepoint argument: every
 * read of `$value` decrypts a live vendor secret into memory, so "how many
 * places touch this model" is literally "how many places a stolen `dd()` would
 * pay off". Callers ask `App\Support\PlatformCredentials`, which is the seam
 * every existing call site already uses.
 *
 * ## Why `value` is hidden as well as encrypted
 *
 * Encryption protects the row at rest — a database dump, a stray backup, a
 * `SELECT *` in a support session. It does nothing about the far likelier
 * accident, which is this object being handed to something that serialises it:
 * a Livewire public property, a JSON response, a log line interpolating a model,
 * an exception report that dumps its context. `$hidden` closes that path, and a
 * test asserts it by round-tripping through `toArray()` rather than by reading
 * the property list — a property list can agree with itself while the behaviour
 * has changed underneath it.
 *
 * @property string $key
 * @property string $value
 * @property ?string $last_four
 * @property CredentialEnvironment $environment
 * @property Carbon $rotated_at
 * @property string $rotated_by
 * @property ?Carbon $last_used_at
 */
final class PlatformCredential extends Model
{
    /**
     * The key a caller already holds is the identifier, exactly as in
     * `PlatformSetting`. See the creating migration for why decision 179's
     * bigint rule does not apply.
     */
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * `rotated_at` is the created/updated timestamp this table actually needs,
     * and it is written explicitly by the store. A pair of automatic ones would
     * add a second, subtly different answer to "when did this key last change".
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * ⚠️ THE ONE ATTRIBUTE THAT MUST NEVER LEAVE THIS OBJECT BY ACCIDENT.
     *
     * @var list<string>
     */
    protected $hidden = ['value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // `38` Part 1: "values encrypted at the application layer (same
            // discipline as OAuth tokens)". Laravel's `encrypted` cast uses
            // APP_KEY, which is the same key TokenService's vault uses — so
            // "same discipline" is literal rather than aspirational.
            //
            // ⚠️ ROTATING APP_KEY MAKES EVERY ROW IN THIS TABLE UNREADABLE, and
            // the failure is a decryption exception on the first vendor call
            // after the deploy, not at boot. The recovery is to paste the keys
            // in again — which is survivable precisely because `.env` remains
            // the documented fallback underneath (D-149's "bootstrap seeds").
            'value' => 'encrypted',
            'environment' => CredentialEnvironment::class,
            'rotated_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
