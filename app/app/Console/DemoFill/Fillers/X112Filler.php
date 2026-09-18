<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Models\User;
use App\Modules\X112\Models\Agency;
use App\Modules\X112\Models\AgencyClient;
use App\Modules\X112\Models\StaffRole;

class X112Filler implements DemoFiller
{
    public const MARKER = 'demo·';

    public function module(): string
    {
        return 'X-112';
    }

    public function fill(Business $business): int
    {
        if (Agency::where('business_id', $business->id)->where('agency_name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $agency = Agency::create([
            'business_id' => $business->id,
            'agency_name' => self::MARKER.'Acme Agency',
            'whitelabel_domain' => 'agency.demo.example',
            'agency_mode' => 'full_service',
        ]);

        AgencyClient::create([
            'business_id' => $business->id,
            'agency_id' => $agency->id,
            'client_business_id' => $business->id,
            'client_name' => self::MARKER.'Client Alpha',
            'status' => 'active',
        ]);

        StaffRole::create([
            'business_id' => $business->id,
            'agency_id' => $agency->id,
            'user_id' => $business->owner_user_id,
            'role' => 'account_manager',
            'is_active' => true,
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        $agencies = Agency::where('business_id', $business->id)
            ->where('agency_name', 'like', self::MARKER.'%')
            ->get();

        if ($agencies->isEmpty()) {
            return 0;
        }

        $count = 0;
        foreach ($agencies as $agency) {
            $count += AgencyClient::where('agency_id', $agency->id)->delete();
            $count += StaffRole::where('agency_id', $agency->id)->delete();
            $agency->delete();
            $count++;
        }

        return $count;
    }
}
