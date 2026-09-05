<?php

declare(strict_types=1);

namespace App\Modules\X155\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    protected $table = 'form_submissions';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'is_spam' => 'boolean',
    ];

    public function formDefinition(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class, 'form_definition_id');
    }
}
