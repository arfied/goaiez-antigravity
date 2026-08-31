<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExportSource;
use App\Enums\ExportStatus;
use App\Models\TenantExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context, the same as every other tenant-owned factory in this schema.
 *
 * @extends Factory<TenantExport>
 */
final class TenantExportFactory extends Factory
{
    protected $model = TenantExport::class;

    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'source' => ExportSource::Owner,
            'status' => ExportStatus::Queued,
            'requested_at' => now(),
        ];
    }

    /**
     * A finished export, ready to download.
     */
    public function ready(): self
    {
        return $this->state(fn (): array => [
            'status' => ExportStatus::Ready,
            'storage_path' => 'exports/test/test.zip',
            'byte_size' => 1024,
            'manifest' => ['included' => [], 'excluded' => []],
            'built_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Every retry exhausted.
     */
    public function failed(): self
    {
        return $this->state(fn (): array => [
            'status' => ExportStatus::Failed,
            'failed_at' => now(),
            'failure_reason' => 'Test failure.',
        ]);
    }
}
