<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FetchMethodCeiling;
use App\Enums\FetchTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The fetch policy for one source (`40` §6.3).
 *
 * NOT tenant-owned, and on the TenancyTest allowlist: whether *we* may
 * fetch Yelp is not a per-tenant question, and no tenant may raise a ceiling
 * that exists to keep us on the right side of someone else's terms.
 *
 * @property string $key
 * @property ?string $class
 * @property FetchMethodCeiling $method_ceiling
 * @property bool $robots_respect
 * @property ?array<string, int> $rate_budget
 * @property ?string $counsel_note_ref
 * @property bool $kill
 * @property ?string $updated_by
 * @property ?Carbon $updated_at
 */
final class FetchSource extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    public const null CREATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * Whether this source may be fetched at this tier right now.
     *
     * Three gates, in the order that costs least to check: the kill switch, the
     * ceiling, and whether we have actually built the tier. A `guided_only`
     * source fails the second one at every tier including F2 — `40` §6.2,
     * "permanently, enforced in the gateway, not in adapter etiquette".
     */
    public function permits(FetchTier $tier): bool
    {
        if ($this->kill) {
            return false;
        }

        return $this->method_ceiling->permits($tier);
    }

    /**
     * Requests allowed in a rolling window, or null when unbudgeted.
     */
    public function budgetPerMinute(): ?int
    {
        return $this->rate_budget['per_minute'] ?? null;
    }

    public function budgetPerDay(): ?int
    {
        return $this->rate_budget['per_day'] ?? null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method_ceiling' => FetchMethodCeiling::class,
            'robots_respect' => 'boolean',
            'rate_budget' => 'array',
            'kill' => 'boolean',
            'updated_at' => 'datetime',
        ];
    }
}
