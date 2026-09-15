<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\X181\Models\QaTicket;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsatAnswer extends Model
{
    protected $table = 'csat_answers';

    protected $guarded = [];

    protected $casts = [
        'is_valid' => 'boolean',
        'score' => 'integer',
        'received_at' => 'datetime',
    ];

    public function qaTicket(): BelongsTo
    {
        return $this->belongsTo(QaTicket::class);
    }
}
