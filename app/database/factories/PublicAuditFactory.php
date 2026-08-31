<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuditStatus;
use App\Models\PublicAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Not tenant-owned — see PublicAudit and docs/DECISIONS.md 177.
 *
 * There is no `business_id` to leave out and no tenant to establish first, so
 * unlike every other factory here this one can be called with no tenant in
 * context at all. Tests that assert the pre-signup path should do exactly that.
 *
 * @extends Factory<PublicAudit>
 */
final class PublicAuditFactory extends Factory
{
    protected $model = PublicAudit::class;

    public function definition(): array
    {
        return [
            'token' => PublicAudit::newToken(),
            'place_id' => 'ChIJ'.fake()->regexify('[A-Za-z0-9_-]{23}'),
            'name_snapshot' => fake()->company(),
            'status' => AuditStatus::Queued,
            'findings' => [],
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'expires_at' => PublicAudit::expiryFromNow(),
        ];
    }

    /**
     * A finished audit with a score and findings, as the public page renders it.
     */
    public function complete(): self
    {
        return $this->state(fn (): array => [
            'status' => AuditStatus::Complete,
            'score' => fake()->numberBetween(20, 95),
            'findings' => [
                [
                    'key' => 'gbp.hours_missing',
                    'severity' => 'attention',
                    'sentence' => 'Your opening hours are not published on Google.',
                ],
            ],
        ]);
    }

    /**
     * An audit that could not produce a result, which the page states plainly
     * rather than hiding (BUILD-PLAN §2.5.3).
     */
    public function failed(): self
    {
        return $this->state(fn (): array => [
            'status' => AuditStatus::Failed,
            'score' => null,
            'findings' => [],
        ]);
    }

    /**
     * Past its retention window — unreachable, and due for pruning.
     */
    public function expired(): self
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Created from a typed business name that has not resolved to a place yet.
     */
    public function unresolved(): self
    {
        return $this->state(fn (): array => [
            'place_id' => null,
            'name_snapshot' => null,
        ]);
    }
}
