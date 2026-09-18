<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X156\Models\IngestRejection;
use App\Modules\X156\Models\IngestRun;
use App\Modules\X156\Models\IngestSource;

class X156Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-156';
    }

    public function fill(Business $business): int
    {
        if (IngestSource::where('business_id', $business->id)->where('source_name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $source = IngestSource::create([
            'business_id' => $business->id,
            'source_type' => 'hubspot',
            'source_name' => self::MARKER.'HubSpot contacts',
        ]);

        IngestRun::create([
            'business_id' => $business->id,
            'source_id' => $source->id,
            'attestation_id' => self::MARKER.'attest_0001',
            'records_ingested' => 240,
        ]);

        IngestRun::create([
            'business_id' => $business->id,
            'source_id' => $source->id,
            'attestation_id' => self::MARKER.'attest_0002',
            'records_ingested' => 118,
        ]);

        IngestRejection::create([
            'business_id' => $business->id,
            'rejection_reason' => self::MARKER.'missing email on the source row',
        ]);

        return 4;
    }

    public function purge(Business $business): int
    {
        $count = 0;
        $count += IngestRejection::where('business_id', $business->id)->where('rejection_reason', 'like', self::MARKER.'%')->delete();
        $sources = IngestSource::where('business_id', $business->id)->where('source_name', 'like', self::MARKER.'%')->get();
        foreach ($sources as $source) {
            $count += IngestRun::where('business_id', $business->id)->where('source_id', $source->id)->delete();
            $source->delete();
            $count++;
        }

        return $count;
    }
}
