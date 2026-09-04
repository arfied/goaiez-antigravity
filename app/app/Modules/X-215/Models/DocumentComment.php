<?php

declare(strict_types=1);

namespace App\Modules\X215\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class DocumentComment extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'document_comments';

    protected $guarded = [];
}
