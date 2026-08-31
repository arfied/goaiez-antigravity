<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditLogEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory). Metadata stays free of personal data — an
 * audit row names what happened, not who it happened to.
 *
 * @extends Factory<AuditLogEntry>
 */
final class AuditLogEntryFactory extends Factory
{
    protected $model = AuditLogEntry::class;

    public function definition(): array
    {
        return [
            'actor' => 'system',
            'action' => 'settings.updated',
            'metadata' => ['field' => 'automation_mode'],
        ];
    }
}
