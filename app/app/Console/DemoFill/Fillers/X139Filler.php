<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X139\Models\AdConnection;
use App\Modules\X139\Models\ConversionUpload;

class X139Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-139';
    }

    public function fill(Business $business): int
    {
        if (AdConnection::where('business_id', $business->id)->where('platform', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        AdConnection::create(['business_id' => $business->id, 'platform' => self::MARKER.'GoogleAds', 'account_id' => '123-456-7890']);
        
        ConversionUpload::create(['business_id' => $business->id, 'job_id' => 1, 'conversion_value_cents' => 100, 'status' => 'uploaded', 'rejection_reason' => null, 'gclid_or_fbc' => self::MARKER.'101']);
        ConversionUpload::create(['business_id' => $business->id, 'job_id' => 2, 'conversion_value_cents' => 200, 'status' => 'uploaded', 'rejection_reason' => null, 'gclid_or_fbc' => self::MARKER.'102']);
        ConversionUpload::create(['business_id' => $business->id, 'job_id' => 3, 'conversion_value_cents' => 300, 'status' => 'rejected', 'rejection_reason' => self::MARKER.'Invalid format', 'gclid_or_fbc' => self::MARKER.'103']);

        return 4;
    }

    public function purge(Business $business): int
    {
        $count = ConversionUpload::where('business_id', $business->id)->where('gclid_or_fbc', 'like', self::MARKER.'%')->delete();
        $count += AdConnection::where('business_id', $business->id)->where('platform', 'like', self::MARKER.'%')->delete();
        return $count;
    }
}
