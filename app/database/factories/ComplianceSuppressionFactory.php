<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ComplianceList;
use App\Enums\OutreachChannel;
use App\Models\ComplianceSuppression;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceSuppression>
 */
final class ComplianceSuppressionFactory extends Factory
{
    protected $model = ComplianceSuppression::class;

    public function definition(): array
    {
        return [
            'list' => ComplianceList::FederalDnc,
            'identifier_type' => OutreachChannel::Sms,
            // A syntactically valid digest. Tests that need a *matching* hash
            // build it with Identifier::hash() — the CHECK constraint refuses
            // anything of another shape, which is what stops a factory default
            // quietly becoming the way rows are written.
            'value_hash' => bin2hex(random_bytes(32)),
            'state' => null,
            'effective_from' => null,
            'source_reference' => 'factory',
            'created_at' => now(),
        ];
    }

    /**
     * A reassignment, which is meaningless without its date.
     */
    public function reassigned(?string $effectiveFrom = null): self
    {
        return $this->state(fn (): array => [
            'list' => ComplianceList::ReassignedNumber,
            'effective_from' => $effectiveFrom ?? now()->toDateString(),
        ]);
    }

    public function litigator(): self
    {
        return $this->state(fn (): array => ['list' => ComplianceList::Litigator]);
    }

    public function stateDnc(string $state): self
    {
        return $this->state(fn (): array => [
            'list' => ComplianceList::StateDnc,
            'state' => strtoupper($state),
        ]);
    }
}
