<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X10\Actions\RoutingRulesEnsureAction;
use App\Modules\X10\Models\RoutingRule;
use App\Modules\X10\Models\Territory;

class X10Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-10';
    }

    public function fill(Business $business): int
    {
        if (RoutingRule::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $rules = app(RoutingRulesEnsureAction::class)->handle($business->id);
        foreach ($rules as $i => $rule) {
            $name = $i === 0 ? 'Rule 1' : $rule->name;
            $rule->update(['name' => self::MARKER.$name]);
        }

        Territory::create(['business_id' => $business->id, 'name' => self::MARKER.'Area 51', 'polygon_geojson' => [], 'zip_codes' => []]);

        return $rules->count() + 1;
    }

    public function purge(Business $business): int
    {
        $rules = RoutingRule::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->get();
        foreach ($rules as $rule) {
            $rule->update(['name' => $rule->rule_type->label()]);
        }
        $count = $rules->count();
        $count += Territory::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->delete();

        return $count;
    }
}
