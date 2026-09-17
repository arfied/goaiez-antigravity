<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X196\Models\ExtensionSession;
use App\Modules\X196\Models\ExtensionInjection;

class X196Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-196';
    }

    public function fill(Business $business): int
    {
        if (ExtensionSession::where('business_id', $business->id)->where('session_token', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $session = ExtensionSession::create([
            'business_id' => $business->id,
            'session_token' => self::MARKER.'session-'.$business->id,
            'is_active' => true,
            'actions_count' => 2,
        ]);

        ExtensionInjection::create([
            'business_id' => $business->id,
            'session_id' => $session->id,
            'source_url' => 'https://partner-directory.example/listing/42',
            'attestation_id' => 'demo·attest-1',
            'prospect_payload' => ['name' => 'demo·Riverside Plumbing'],
        ]);

        ExtensionInjection::create([
            'business_id' => $business->id,
            'session_id' => $session->id,
            'source_url' => 'https://neighborhood-forum.example/thread/17',
            'attestation_id' => 'demo·attest-2',
            'prospect_payload' => ['name' => 'demo·Oak Street Dental'],
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        $injections = ExtensionInjection::where('business_id', $business->id)->where('attestation_id', 'like', self::MARKER.'%')->delete();
        $sessions = ExtensionSession::where('business_id', $business->id)->where('session_token', 'like', self::MARKER.'%')->delete();

        return $injections + $sessions;
    }
}
