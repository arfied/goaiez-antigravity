<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X206\Models\Credential;
use App\Modules\X206\Models\CredentialReveal;

class X206Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-206';
    }

    public function fill(Business $business): int
    {
        if (Credential::where('business_id', $business->id)->where('service_name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $c1 = Credential::create(['business_id' => $business->id, 'service_name' => self::MARKER.'Auth', 'encrypted_secret' => 'secret', 'key_hint' => self::MARKER.'hint1']);
        $c2 = Credential::create(['business_id' => $business->id, 'service_name' => self::MARKER.'Payment', 'encrypted_secret' => 'secret2', 'key_hint' => self::MARKER.'hint2']);

        CredentialReveal::create(['business_id' => $business->id, 'credential_id' => $c1->id, 'service_name' => self::MARKER.'Auth', 'status' => 'permitted']);

        return 3;
    }

    public function purge(Business $business): int
    {
        $count = CredentialReveal::where('business_id', $business->id)->where('service_name', 'like', self::MARKER.'%')->delete();
        $count += Credential::where('business_id', $business->id)->where('service_name', 'like', self::MARKER.'%')->delete();

        return $count;
    }
}
