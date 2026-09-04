<?php

declare(strict_types=1);

namespace App\Modules\X155\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'form_submissions';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'is_spam' => 'boolean',
    ];
}
