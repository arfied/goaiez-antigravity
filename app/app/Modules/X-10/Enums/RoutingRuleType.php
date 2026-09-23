<?php

declare(strict_types=1);

namespace App\Modules\X10\Enums;

enum RoutingRuleType: string
{
    case RETURNING_CALLER = 'returning_caller';
    case TERRITORY = 'territory';
    case WORKLOAD = 'workload';
    case DEFAULT_STAFF = 'default_staff';

    public function label(): string
    {
        return match ($this) {
            self::RETURNING_CALLER => 'Send returning callers to the person who helped them last',
            self::TERRITORY => 'Send to the territory owner',
            self::WORKLOAD => 'Send to the person with the lightest workload',
            self::DEFAULT_STAFF => 'Send to a default staff member if nothing else matches',
        };
    }
}
