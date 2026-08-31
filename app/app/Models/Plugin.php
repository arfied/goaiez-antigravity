<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\PluginType;
use Database\Factories\PluginFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An embeddable widget (DATA-MODEL §5.11).
 *
 * embed_key is the globally-unique opaque join key the embed script presents
 * before any tenant is known — always a random UUID, never derived from
 * tenant data, same pattern as businesses.pixel_tenant_id.
 *
 * ---------------------------------------------------------------------------
 * STILL TENANT-SCOPED, UNLIKE `FeedbackPage`, WHICH SOLVES THE SAME PROBLEM
 * ---------------------------------------------------------------------------
 * Both resolve a tenant from a public opaque key, so both hit decision 318's
 * circularity: `TenantScope` calls `Tenancy::idOrFail()`, which cannot run on
 * the query whose answer *establishes* the tenant. `FeedbackPage` answers that
 * by carrying no scope at all and joining the TenancyTest allowlist.
 *
 * This model keeps the scope and `WidgetPlugins::resolve()` drops it in that
 * one method. The asymmetry is deliberate: `idOrFail()` **throws** rather than
 * filtering to nothing, so a scoped model fails loudly on any path that forgot
 * a tenant, while an unscoped one quietly returns whatever `public_read`
 * permits — which is every row on the table. One audited opt-out is a smaller
 * surface than a ninth allowlist entry, and `WidgetPlugins` is the only class
 * permitted to touch `Plugin::query()` regardless, enforced by an
 * ArchitectureTest lint.
 *
 * @property-read int $id
 * @property int $business_id
 * @property ?int $location_id
 * @property PluginType $type
 * @property string $embed_key
 * @property ?array<string, mixed> $config
 *
 * ⚠️ `allowed_domains` IS `array`, NOT `list<string>`, and the looser type is
 * deliberate — `Review`'s own docblock records the same reasoning for `themes`
 * and `moderation_flags`. `WidgetPlugins::setAllowedDomains()` writes a
 * `list<string>`, but nothing enforces that shape on the jsonb column itself: a
 * repair script, a seeder or a future admin screen can put anything in it. A
 * `list<string>` annotation would make PHPStan call the runtime `is_string()`
 * guard in originIsAllowed() redundant and delete the one check standing
 * between a malformed row and a host comparison against an array.
 * @property ?array<int|string, mixed> $allowed_domains
 * @property int $min_stars_to_show
 * @property ?string $version
 * @property ?Carbon $created_at
 */
final class Plugin extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<PluginFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * `min_stars_to_show`, `type` and `embed_key` are guarded on purpose, and
     * `WidgetPlugins` is the "else" that forceFill()s them. The first has a
     * legal dimension (FTC review-suppression), the second is the vocabulary,
     * and the third is a permanent public identifier — none of the three is
     * something a future `fill()` from a request body should be able to reach.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id', 'type', 'embed_key', 'min_stars_to_show'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PluginType::class,
            'config' => 'array',
            'allowed_domains' => 'array',
            'min_stars_to_show' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
