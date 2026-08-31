<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PixelKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PixelKey>
 *
 * ⚠️ **A FACTORY IS NOT A WRITER, AND THIS ONE IS WHY THE COLUMN IT REPLACES
 * WENT UNNOTICED.** `BusinessFactory` filled `pixel_tenant_id` while nothing in
 * `app/` ever did, so every test that built a tenant through the factory
 * exercised a key no real tenant had — decision 272's shape, hidden by the
 * harness. `PixelKeys::ensureFor()` is the writer; this exists for tests that
 * need a key without provisioning a whole tenant, and `pixelTenant()` in
 * `tests/Support/pixel_helpers.php` deliberately does **not** use it.
 */
final class PixelKeyFactory extends Factory
{
    protected $model = PixelKey::class;

    /**
     * ⚠️ **DOES NOT DEFAULT `business_id`** — `BelongsToTenant` fills it from the
     * tenant in context, exactly as `PluginFactory` does. Naming it here would let
     * a test mint a key against a tenant its session is not in, which is the one
     * mistake this key must not make.
     */
    public function definition(): array
    {
        return [
            // Always random, never derived from tenant data — the model, the
            // migration and `Plugin::$embed_key` all say the same thing for the
            // same reason: it is presented by an anonymous browser before any
            // tenant is known.
            'key' => (string) Str::uuid(),
        ];
    }
}
