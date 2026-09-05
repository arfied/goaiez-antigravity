<?php

declare(strict_types=1);

namespace App\Modules\X155\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormDefinition extends Model
{
    protected $table = 'form_definitions';

    protected $guarded = [];

    protected $casts = [
        'steps' => 'array',
        'schema' => 'array',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }
}
