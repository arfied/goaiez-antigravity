<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\CMail\Models\MailDomain;
use App\Modules\CMail\Models\WarmupCalendar;

class CMailFiller implements DemoFiller
{
    public function module(): string
    {
        return 'C-Mail';
    }

    public function fill(Business $business): int
    {
        if (MailDomain::where('business_id', $business->id)->where('domain_name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $domain = MailDomain::create([
            'business_id' => $business->id,
            'domain_name' => self::MARKER.'mail.example.com',
            'dkim_status' => 'pending',
            'spf_status' => 'pending',
            'dmarc_status' => 'pending',
            'is_marketing_paused' => false,
            'complaint_rate' => 0,
        ]);

        WarmupCalendar::create([
            'business_id' => $business->id,
            'mail_domain_id' => $domain->id,
            'current_day' => 3,
            'daily_allowance' => 200,
            'sent_today' => 120,
            'is_warmed' => false,
        ]);

        return 2;
    }

    public function purge(Business $business): int
    {
        $domainIds = MailDomain::where('business_id', $business->id)
            ->where('domain_name', 'like', self::MARKER.'%')
            ->pluck('id');

        $calendars = WarmupCalendar::whereIn('mail_domain_id', $domainIds)->delete();
        $domains = MailDomain::whereIn('id', $domainIds)->delete();

        return $calendars + $domains;
    }
}
