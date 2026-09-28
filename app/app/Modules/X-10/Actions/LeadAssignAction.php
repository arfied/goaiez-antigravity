<?php

declare(strict_types=1);

namespace App\Modules\X10\Actions;

use App\Models\Business;
use App\Modules\X10\Enums\RoutingRuleType;
use App\Modules\X10\Events\LeadAssigned;
use App\Modules\X10\Models\Assignment;
use App\Modules\X10\Models\RoutingRule;
use App\Modules\X10\Models\Territory;
use Illuminate\Support\Facades\Event;

final class LeadAssignAction
{
    public function __construct(
        private readonly RoutingRulesEnsureAction $ensureRules,
    ) {}

    /**
     * Assigns lead according to routing rules: returning caller affinity, polygon territory, or workload balance.
     */
    public function handle(
        int $businessId,
        int $leadId,
        ?int $previousOwnerStaffId = null,
        ?string $zipCode = null,
        array $staffWorkloads = []
    ): Assignment {
        $rules = $this->ensureRules->handle($businessId);

        $assignedStaffId = null;
        $reason = null;

        foreach ($rules->where('is_active', true)->sortBy('priority') as $rule) {
            $assignedStaffId = match ($rule->rule_type) {
                RoutingRuleType::RETURNING_CALLER => $this->matchReturningCaller($previousOwnerStaffId),
                RoutingRuleType::TERRITORY => $this->matchTerritory($businessId, $zipCode),
                RoutingRuleType::WORKLOAD => $this->matchWorkload($staffWorkloads),
                RoutingRuleType::DEFAULT_STAFF => $this->matchDefaultStaff($businessId, $rule),
            };

            if ($assignedStaffId !== null) {
                $reason = $rule->rule_type->value;
                break;
            }
        }

        if ($assignedStaffId === null) {
            $defaultRule = $rules->where('rule_type', RoutingRuleType::DEFAULT_STAFF)->first();
            $assignedStaffId = $this->matchDefaultStaff($businessId, $defaultRule);
            $reason = RoutingRuleType::DEFAULT_STAFF->value;
        }

        $assignment = Assignment::create([
            'business_id' => $businessId,
            'lead_id' => $leadId,
            'assigned_staff_id' => $assignedStaffId,
            'assignment_reason' => $reason,
            'status' => 'active',
        ]);

        Event::dispatch(new LeadAssigned($businessId, $leadId, $assignedStaffId, $reason));

        return $assignment;
    }

    private function matchReturningCaller(?int $previousOwnerStaffId): ?int
    {
        return $previousOwnerStaffId;
    }

    private function matchTerritory(int $businessId, ?string $zipCode): ?int
    {
        if ($zipCode === null) {
            return null;
        }

        $territory = Territory::where('business_id', $businessId)->get()->first(function ($t) use ($zipCode) {
            return is_array($t->zip_codes) && in_array($zipCode, $t->zip_codes, true);
        });

        return $territory?->assigned_staff_id;
    }

    private function matchWorkload(array $staffWorkloads): ?int
    {
        if (empty($staffWorkloads)) {
            return null;
        }

        asort($staffWorkloads);

        return (int) array_key_first($staffWorkloads);
    }

    private function matchDefaultStaff(int $businessId, ?RoutingRule $rule): int
    {
        if ($rule && isset($rule->settings['staff_id'])) {
            return (int) $rule->settings['staff_id'];
        }

        $business = Business::find($businessId);

        return (int) $business->owner_user_id;
    }
}
