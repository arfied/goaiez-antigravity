<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\ExportSource;
use App\Enums\ExportStatus;
use App\Jobs\BuildTenantExportJob;
use Database\Factories\TenantExportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One built (or building, or failed) "Download my data" ZIP (`28` §3.7).
 *
 * ⚠️ **A REBUILD IS A NEW ROW, NEVER A `queued` ROW TURNED BACK FROM `ready`.**
 * {@see BuildTenantExportJob} never resets a `Ready` row — the CHECK
 * `tenant_exports_ready_is_whole` would refuse it losing its path anyway, and a
 * row that once promised a download staying at that url is what makes the
 * expiring-link rule mean something. `App\Services\Export\ExportBuilder` is
 * the only writer, held there by a chokepoint lint in
 * `tests/Feature/Architecture/CrmTest.php`.
 *
 * ⚠️ **`customer_id` IS THE SCOPE, AND `null` IS AN ACCOUNT-WIDE EXPORT.** A set
 * value is `44` §10's per-contact export and `28` §3.7's *"Advanced adds
 * per-object exports"*. There is no companion status word saying which — the
 * column already answers it, and a second spelling of one fact is what drifts.
 * The creating migration for that column carries the reasoning; the load-bearing
 * half is that **the scope is fixed before a byte is assembled**, so the object
 * a row names is already the right size and the download path has nothing left
 * to filter.
 *
 * @property ?int $customer_id
 * @property ExportSource $source
 * @property ExportStatus $status
 * @property ?array<string, mixed> $manifest
 * @property Carbon $requested_at
 * @property ?Carbon $built_at
 * @property ?Carbon $expires_at
 * @property ?Carbon $failed_at
 */
final class TenantExport extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<TenantExportFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => ExportSource::class,
            'status' => ExportStatus::class,
            'manifest' => 'array',
            'requested_at' => 'datetime',
            'built_at' => 'datetime',
            'expires_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Which contact this build is about, or null for the whole account.
     *
     * ⚠️ **ASKED OF THE ROW BY THE DOWNLOAD PATH RATHER THAN DERIVED FROM THE
     * URL**, and that direction is the point: a link carries whatever it was
     * minted with, and the row carries what was actually built.
     * `TenantExportDownloadController` compares the two and refuses any
     * disagreement — see its docblock for the breach that closes.
     */
    public function contactScope(): ?int
    {
        return $this->customer_id === null ? null : (int) $this->customer_id;
    }

    /**
     * Whether the link is good right now — built, and not past its seven days.
     *
     * ⚠️ **THE ONLY PLACE THIS QUESTION IS ANSWERED, AND THE DOWNLOAD ROUTE ASKS
     * IT RATHER THAN COMPARING `expires_at` ITSELF.** A second comparison
     * drifting from this one — `>=` where this is `>`, or forgetting the status
     * half — is exactly how a link outlives its seven days by one comparison
     * operator, silently, on the one path that hands a file to a browser.
     */
    public function isDownloadable(): bool
    {
        return $this->status === ExportStatus::Ready
            && $this->expires_at instanceof Carbon
            && $this->expires_at->isFuture();
    }
}
