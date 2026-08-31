<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\BoostScoreHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Boost Score observation (DATA-MODEL §5.12). recorded_at is the datum;
 * the table has no other timestamps at all.
 */
final class BoostScoreHistory extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<BoostScoreHistoryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'boost_score_history';

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
            'score' => 'integer',
            'recorded_at' => 'datetime',
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
