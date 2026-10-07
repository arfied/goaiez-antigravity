<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class WebstudioSite extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'webstudio_sites';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'editor_token' => 'encrypted',
            'publish_started_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public const NEVER = 'never';

    public const PUBLISHING = 'publishing';

    public const PUBLISHED = 'published';

    public const FAILED = 'failed';

    public const SOURCE_CLONE = 'clone';

    public const SOURCE_TEMPLATE = 'template';
}
