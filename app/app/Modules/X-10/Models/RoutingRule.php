<?php

declare(strict_types=1);

namespace App\Modules\X10\Models;

use App\Modules\X10\Enums\RoutingRuleType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property RoutingRuleType $rule_type
 * @property array $settings
 * @property int $priority
 * @property bool $is_active
 */
class RoutingRule extends Model
{
    protected $table = 'routing_rules';

    protected $guarded = [];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
        'rule_type' => RoutingRuleType::class,
        'settings' => 'array',
    ];
}
