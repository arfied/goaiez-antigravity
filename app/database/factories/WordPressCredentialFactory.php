<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\WordPressCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * `business_id` is deliberately absent — `BelongsToTenant` fills it from
 * context, and a factory that created its own parent would make a second tenant.
 *
 * ⛔ **NO REAL SECRET EVER, AND `.test` IS NOT DECORATION.** `CLAUDE.md`: a
 * credential never reaches a test fixture. The password below is a fixed,
 * obviously-fake string that appears nowhere else, and the host is in the
 * reserved `.test` TLD (RFC 6761) so a fixture that escaped `Http::fake()` would
 * resolve to nothing rather than to somebody's live WordPress.
 *
 * ⚠️ **THIS FACTORY IS FOR ISOLATION AND SHAPE TESTS, NOT FOR CONNECTING.**
 * `WordPressCredentials::connect()` is the only supported way to create one of
 * these, because it is what runs §19.7's least-privilege probe. A test that
 * reaches for the factory to get a connected site is testing the table rather
 * than the gate in front of it.
 *
 * @extends Factory<WordPressCredential>
 */
final class WordPressCredentialFactory extends Factory
{
    protected $model = WordPressCredential::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'site_url' => 'https://example.test',
            'rest_root' => 'https://example.test/wp-json/',
            'username' => 'goaiez-editor',
            'application_password' => 'fixture only never a real secret',
            'wp_user_id' => 7,
            'wp_roles' => ['editor'],
            'verified_at' => now(),
        ];
    }
}
