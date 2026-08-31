<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\DeviceClass;
use App\Enums\WebVital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * `28` §4.3's real-user speed measurements, as a daily histogram. The creating
 * migration carries the argument for why this stores buckets and counts rather
 * than a percentile.
 *
 * `$primaryKey` is `business_id` only so Eloquent has something to answer with;
 * the real key is (`business_id`, `day`, `metric`, `device_type`, `bucket`) and
 * `find()` is meaningless here — [[L2FactPageDaily]] and [[L2FactSession]] have
 * the same shape. This model **reads**; the write path is
 * [[\App\Services\Warehouse\Replayer]] and the only reader is
 * [[\App\Services\Warehouse\SiteVitals]].
 *
 * @property int $business_id
 * @property Carbon $day
 * @property WebVital $metric
 * @property DeviceClass $device_type
 * @property int $bucket
 * @property int $samples
 */
final class L2FactVitalDaily extends Model implements TenantScoped
{
    use BelongsToTenant;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'l2_fact_vital_daily';

    protected $primaryKey = 'business_id';

    protected $keyType = 'int';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            'metric' => WebVital::class,
            'device_type' => DeviceClass::class,
            'bucket' => 'integer',
            'samples' => 'integer',
        ];
    }
}
