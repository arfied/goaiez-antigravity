<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\WordPressCredentialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A working login to a tenant's own WordPress site.
 *
 * ⛔ **NOTHING SHOULD REACH THIS MODEL DIRECTLY.**
 * `App\Services\Actuation\WordPress\WordPressCredentials` is its only reader and
 * writer, held by a chokepoint lint in
 * `tests/Feature/Architecture/ActuationTest.php`. `PlatformCredential`'s
 * docblock states the reason and it is sharper here: every read of
 * `application_password` decrypts a live write credential for somebody else's
 * website into memory, so "how many places touch this model" is literally "how
 * many places a stray `dd()` would pay off" — and the property being protected
 * is not ours to lose.
 *
 * ## Why the `encrypted` cast rather than `OauthConnection`'s explicit shape
 *
 * ⚠️ **The two answers are different because the two problems are.**
 * `OauthConnection` stores ciphertext in `*_enc` columns and decrypts only
 * inside `TokenService`, deliberately avoiding a cast so "the model would hold
 * plaintext the moment anything touched it" cannot happen. That argument buys
 * its safety from a *service* that has to exist anyway — the vault refreshes
 * tokens, so there is a natural single reader.
 *
 * Here there is nothing to refresh: an Application Password does not expire and
 * has no refresh grant. A hand-rolled `*_enc` column would therefore mean
 * `Crypt::decryptString()` at the one call site that needs it and a column name
 * whose suffix is the only thing saying it is ciphertext. **The cast plus
 * `$hidden` plus the chokepoint lint gives the same single reader with none of
 * that**, and it is `PlatformCredential`'s shape, which is the model this most
 * resembles: a bare secret with no lifecycle.
 *
 * ⚠️ **ROTATING `APP_KEY` MAKES EVERY ROW HERE UNREADABLE**, and the failure is
 * a decryption exception on the first write attempt after the deploy rather than
 * at boot. The recovery is the owner reconnecting, which is survivable because
 * reconnecting is a supported act rather than a repair.
 *
 * ## Why `$hidden` as well as encryption
 *
 * Encryption protects the row at rest. `$hidden` protects it from the far
 * likelier accident — a Livewire public property, a JSON response, a log line
 * interpolating a model, an exception report dumping its context. Both are
 * asserted by round-tripping through `toArray()` and `toJson()` rather than by
 * reading the property list, because a property list can agree with itself while
 * the behaviour has changed underneath it.
 *
 * @property-read int $id
 * @property int $location_id
 * @property string $site_url
 * @property string $rest_root
 * @property string $username
 * @property string $application_password
 * @property int $wp_user_id
 * @property list<string> $wp_roles
 * @property Carbon $verified_at
 */
final class WordPressCredential extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<WordPressCredentialFactory> */
    use HasFactory;

    /**
     * ⛔ **NAMED EXPLICITLY, AND THE DEFAULT IS NOT A NEAR MISS BUT A DIFFERENT
     * TABLE.** Laravel's snake-casing of `WordPressCredential` is
     * `word_press_credentials` — the capital `P` becomes its own word — and the
     * migration creates `wordpress_credentials`, which is how the product spells
     * itself. Every query would have failed at runtime against a relation that
     * does not exist, which is the *safe* direction and still a defect the first
     * test run found rather than a reviewer.
     *
     * ⚠️ **THE ALTERNATIVE WAS TO SPELL THE MODEL `WordpressCredential`** and
     * let the convention hold. It was refused because the vendor's own name has
     * the capital — `19` §3.3, doc `41` Part 2 and every docblock in this slice
     * write "WordPress" — and a class named after a product it misspells is a
     * cost paid on every read to save one line here.
     *
     * @var string
     */
    protected $table = 'wordpress_credentials';

    /**
     * `business_id` stays guarded: it is the tenant key, and mass-assigning it
     * from request input is how a row crosses the boundary. `BelongsToTenant`
     * fills it from context on create.
     *
     * ⚠️ **`location_id` IS GUARDED TOO**, and for a reason `Location`'s own
     * `timezone` argument names: it decides *which website* a credential unlocks.
     * Mass-assigning it from input would let a request point one site's login at
     * another location's row — inside the same tenant, where no global scope and
     * no policy would notice.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id', 'location_id'];

    /**
     * ⚠️ THE ONE ATTRIBUTE THAT MUST NEVER LEAVE THIS OBJECT BY ACCIDENT.
     *
     * @var list<string>
     */
    protected $hidden = ['application_password'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'application_password' => 'encrypted',
            'wp_roles' => 'array',
            'wp_user_id' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
