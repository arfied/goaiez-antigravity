<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CapturedBy;
use App\Enums\CaptureSurface;
use App\Enums\ConsentType;
use App\Enums\OutreachChannel;
use App\Models\ConsentRecord;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A platform-captured SMS consent, fully proved — the Lane A shape.
 *
 * proof carries ip_hash, never an IP. That is a fixture rule as much as a
 * production rule: raw IP is never stored anywhere, including tests.
 *
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory).
 *
 * @extends Factory<ConsentRecord>
 */
final class ConsentRecordFactory extends Factory
{
    protected $model = ConsentRecord::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'channel' => OutreachChannel::Sms,
            'consent_type' => ConsentType::ExpressWritten,
            'captured_by' => CapturedBy::Platform,
            'capture_surface' => CaptureSurface::FeedbackPage,
            'disclosure_version' => '2026-07-01',
            'method' => 'checkbox',
            'proof' => [
                'ip_hash' => fake()->sha256(),
                'ts' => now()->toIso8601String(),
                'url' => 'https://example.test/feedback',
                'user_agent' => 'Mozilla/5.0 (test)',
                'checkbox_state' => 'checked_by_user',
                'wording' => '2026-07-01',
            ],
        ];
    }

    /**
     * Tenant-asserted consent — the Lane B shape, requiring the tenant's own
     * TCR brand before it is sendable.
     */
    public function tenantCaptured(): self
    {
        return $this->state(fn (): array => [
            'captured_by' => CapturedBy::Tenant,
            'capture_surface' => CaptureSurface::Import,
            'method' => 'attestation',
        ]);
    }
}
