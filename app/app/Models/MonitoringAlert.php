<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\MonitoringAlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something the watchers found (DATA-MODEL §5.12). resolved_at null means
 * the alert is still open.
 *
 * @property-read int $id
 * @property int $business_id
 * @property ?int $location_id
 * @property string $alert_type
 * @property ?string $severity
 * @property string $title
 * @property ?array<string, mixed> $detail
 * @property ?Carbon $notified_at
 * @property ?Carbon $resolved_at
 * @property ?Carbon $created_at
 */
final class MonitoringAlert extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<MonitoringAlertFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

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
            'detail' => 'array',
            'notified_at' => 'datetime',
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
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
