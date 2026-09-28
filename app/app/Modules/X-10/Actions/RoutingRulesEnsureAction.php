<?php

declare(strict_types=1);

namespace App\Modules\X10\Actions;

use App\Modules\X10\Enums\RoutingRuleType;
use App\Modules\X10\Models\RoutingRule;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Database\Eloquent\Collection;

class RoutingRulesEnsureAction
{
    public function __construct(
        private readonly DefaultsRegistry $registry,
    ) {}

    /**
     * @return Collection<int, RoutingRule>
     */
    public function handle(int $businessId): Collection
    {
        $order = explode(',', $this->registry->string('routing.default_order'));
        $priority = 1;

        foreach ($order as $type) {
            $ruleType = RoutingRuleType::from($type);

            RoutingRule::firstOrCreate(
                [
                    'business_id' => $businessId,
                    'rule_type' => $ruleType,
                ],
                [
                    'name' => $ruleType->label(),
                    'priority' => $priority,
                    'is_active' => true,
                    'settings' => $ruleType === RoutingRuleType::DEFAULT_STAFF ? ['staff_id' => null] : null,
                ]
            );
            $priority++;
        }

        return RoutingRule::where('business_id', $businessId)->orderBy('priority')->get();
    }
}
