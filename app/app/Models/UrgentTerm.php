<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Services\Assistant\UrgentTerms;
use Illuminate\Database\Eloquent\Model;

/**
 * One word that means drop everything — T176 P5, R13's skill 9.
 *
 * ⛔ **READ AND WRITTEN ONLY THROUGH {@see UrgentTerms}, AND A CHOKEPOINT LINT
 * HOLDS THAT** (`tests/Feature/Architecture/PricesTest.php`). The matching rule
 * — case-insensitive, on whole words, against the tenant's own list and nothing
 * else — is the entire behaviour of this table, and a second reader is a second
 * matching rule. Two rules for "is this urgent?" is how one screen shows a term
 * as live while the escalation never fires on it.
 *
 * @property int $id
 * @property int $business_id
 * @property string $term
 */
final class UrgentTerm extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'business_id',
        'term',
    ];
}
