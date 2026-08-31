<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Pixel\PixelDeliveryHealth;
use Database\Factories\PixelDeliverySampleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One minute of one bundle version's pageview/`js_error` counts — the writer
 * behind §10's *"auto-halt on JS-error regression >0.5%"*. See the creating
 * migration and {@see PixelDeliveryHealth}, its only reader
 * and writer.
 *
 * @property int $id
 * @property string $build_token
 * @property Carbon $bucket
 * @property int $pageviews
 * @property int $js_errors
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class PixelDeliverySample extends Model
{
    /** @use HasFactory<PixelDeliverySampleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bucket' => 'datetime',
            'pageviews' => 'integer',
            'js_errors' => 'integer',
        ];
    }
}
