<?php

declare(strict_types=1);

namespace App\Modules\X204\Models;

use App\Enums\SendRefusalReason;
use Illuminate\Database\Eloquent\Model;

class SendPermit extends Model
{
    protected $table = 'send_permits';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->permit_status === 'refused' && $model->refusal_reason) {
                if (! in_array($model->refusal_reason, array_column(SendRefusalReason::cases(), 'value'), true)) {
                    throw new \InvalidArgumentException('Refusal reason must be a valid P-060 code. Got: '.$model->refusal_reason);
                }
            }
        });
    }
}
