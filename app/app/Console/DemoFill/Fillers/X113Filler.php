<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X113\Models\Role;
use App\Modules\X113\Models\StaffUser;

class X113Filler implements DemoFiller
{
    public const MARKER = 'demo·';

    public function module(): string
    {
        return 'X-113';
    }

    public function fill(Business $business): int
    {
        if (Role::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $role = Role::create([
            'business_id' => $business->id,
            'name' => self::MARKER . 'Field technician',
            'description' => 'Runs jobs and updates the customer.',
        ]);

        StaffUser::create([
            'business_id' => $business->id,
            'name' => self::MARKER . 'Marcus Reed',
            'email' => 'marcus.reed@partner-demo.example',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        StaffUser::create([
            'business_id' => $business->id,
            'name' => self::MARKER . 'Dana Whitfield',
            'email' => 'dana.whitfield@partner-demo.example',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        $staffCount = StaffUser::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->delete();
        $roleCount = Role::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->delete();
        
        return $staffCount + $roleCount;
    }
}
