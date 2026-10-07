<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SiteCloneJob extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'site_clone_jobs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'logs' => 'array',
            'heartbeat_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const ACTIVE = [self::QUEUED, self::RUNNING];
}
