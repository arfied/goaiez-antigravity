<?php

declare(strict_types=1);

namespace App\Modules\X102\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ChatLead extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'chat_leads';

    protected $guarded = [];
}
