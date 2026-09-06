<?php

declare(strict_types=1);

namespace App\Tests\Modules\CMail;

use App\Modules\CMail\Actions\EmailHaltSeedAction;
use App\Modules\CMail\Models\MailDomain;
use App\Modules\CMail\Models\MailEvent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class G1105Test extends TestCase
{
    #[Test]
    public function g11_05_halts_marketing_when_complaint_rate_reaches_seed(): void
    {
        $business = self::provisionTenant();
        $businessId = $business->id;

        $domain = MailDomain::create([
            'business_id' => $businessId,
            'domain_name' => 'test-halt.com',
            'is_marketing_paused' => false,
            'complaint_rate' => 0.0,
        ]);

        // 1000 sent, 1 complaint = 0.001 (0.10%)
        for ($i = 0; $i < 1000; $i++) {
            MailEvent::create([
                'business_id' => $businessId,
                'mail_domain_id' => $domain->id,
                'event_type' => 'sent',
                'recipient_email' => 't@example.com',
                'subject' => 'test',
            ]);
        }

        MailEvent::create([
            'business_id' => $businessId,
            'mail_domain_id' => $domain->id,
            'event_type' => 'complained',
            'recipient_email' => 't@example.com',
            'subject' => 'test',
        ]);

        $action = new EmailHaltSeedAction;
        $updatedDomain = $action->handle($businessId, $domain->id);

        $this->assertTrue($updatedDomain->is_marketing_paused);
        $this->assertEquals(0.0010, $updatedDomain->complaint_rate);
    }
}
