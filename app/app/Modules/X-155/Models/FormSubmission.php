<?php

declare(strict_types=1);

namespace App\Modules\X155\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    protected $table = 'form_submissions';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'is_spam' => 'boolean',
    ];
}
