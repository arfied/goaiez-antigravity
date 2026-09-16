<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X110\Models\Visit;
use App\Modules\X110\Models\Session;
use App\Modules\X110\Models\PixelEvent;
use App\Modules\X110\Models\CwvSample;

class X110Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-110';
    }

    public function fill(Business $business): int
    {
        if (Visit::where('business_id', $business->id)->where('visitor_id', self::MARKER.'Vis')->exists()) {
            return 0;
        }

        $visit1 = Visit::create(['business_id' => $business->id, 'visitor_id' => self::MARKER.'Vis', 'landing_page' => '/', 'created_at' => now()]);
        $visit2 = Visit::create(['business_id' => $business->id, 'visitor_id' => self::MARKER.'Vis2', 'landing_page' => '/about', 'created_at' => now()]);

        $session = Session::create(['business_id' => $business->id, 'visit_id' => $visit1->id, 'started_at' => now(), 'session_token' => self::MARKER.'Token']);

        PixelEvent::create(['business_id' => $business->id, 'session_id' => $session->id, 'event_name' => 'form.abandoned', 'payload' => ['form_id' => 'contact_form', 'abandoned_field' => self::MARKER.'field']]);
        PixelEvent::create(['business_id' => $business->id, 'session_id' => $session->id, 'event_name' => 'pageview', 'payload' => []]);

        CwvSample::create(['business_id' => $business->id, 'lcp_ms' => 1234, 'fid_ms' => 12, 'cls_value' => 0.05, 'url' => self::MARKER.'url']);

        return 6;
    }

    public function purge(Business $business): int
    {
        $count = PixelEvent::where('business_id', $business->id)->where('payload', 'like', '%'.self::MARKER.'%')->delete();
        $visits = Visit::where('business_id', $business->id)->where('visitor_id', 'like', self::MARKER.'%')->pluck('id');
        $count += Session::where('business_id', $business->id)->whereIn('visit_id', $visits)->delete();
        $count += Visit::where('business_id', $business->id)->where('visitor_id', 'like', self::MARKER.'%')->delete();
        $count += CwvSample::where('business_id', $business->id)->where('url', 'like', self::MARKER.'%')->delete();
        return $count;
    }
}
