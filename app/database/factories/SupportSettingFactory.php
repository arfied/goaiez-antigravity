<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CallRoutingMode;
use App\Models\SupportSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * ⛔ **THIS FACTORY SEEDED SIX URGENT WORDS UNTIL 2026-08-21 AND THE LINT WRITTEN
 * TO FORBID EXACTLY THAT READ THIS FILE AND COULD NOT SEE THEM** (6884).
 * `tests/Feature/Architecture/PricesTest.php`'s *"nothing seeds a price, an urgent word or an emergency
 * number"* has `database/factories` in its subject list, so it opened this file
 * on every run — and searched it for `urgent_terms` and `UrgentTerm`, while the
 * words sat under `emergency_keywords` on a different table. **A lint that names
 * the right table cannot see the wrong one**, which is why the column is gone
 * rather than merely unseeded: one vocabulary of urgency is what makes that lint
 * true, and two tables is what made it unfalsifiable.
 *
 * @extends Factory<SupportSetting>
 */
final class SupportSettingFactory extends Factory
{
    protected $model = SupportSetting::class;

    public function definition(): array
    {
        return [
            'business_hours' => ['mon' => ['09:00', '17:00']],
            'escalation_contacts' => [],
            'blocked_topics' => [],
            'agent_schedule' => [],
            'on_call_numbers' => [],
            'call_routing_mode' => CallRoutingMode::TrackingOnly,
        ];
    }
}
