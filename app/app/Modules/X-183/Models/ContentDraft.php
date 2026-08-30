<?php

declare(strict_types=1);

namespace App\Modules\X183\Models;

use Illuminate\Database\Eloquent\Model;

class ContentDraft extends Model
{
    protected $table = 'content_drafts';

    protected $guarded = [];

    protected $casts = [
        'is_case_study' => 'boolean',
        'has_double_consent' => 'boolean',
        'is_approved' => 'boolean',
        'is_published' => 'boolean',
    ];

    public function gateResults()
    {
        return $this->hasMany(GateResult::class, 'draft_id');
    }
}
