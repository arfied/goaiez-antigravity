<?php

declare(strict_types=1);

namespace Tests\Modules\CMail;

use App\Modules\CMail\Actions\EmailDnsCheckAction;
use App\Modules\CMail\Actions\EmailSendAction;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmailSendActionGateTest extends TestCase
{
    public function test_b_and_c_helper_not_called_refuses_send(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Gate Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $dnsAction = new EmailDnsCheckAction;
        $domain = $dnsAction->handle($biz->id, 'gate-b.apex-air.com');

        // (c) The witness for the injection: resolve the action through the container
        $action = app(EmailSendAction::class);

        // (b) a marketing send returns status => 'refused_no_feedback_signal'
        $result = $action->handle(
            businessId: $biz->id,
            mailDomainId: $domain->id,
            recipientEmail: 'test-b@acme.com',
            subject: 'New Mktg',
            sendType: 'marketing'
        );

        $this->assertSame('refused_no_feedback_signal', $result['status']);
        $this->assertSame('CUSTOMER_MAIL_NOT_DELIVERABLE', $result['refusal_code']);

        // and assertDatabaseMissing on mail_events for event_type => 'sent'
        $this->assertDatabaseMissing('mail_events', [
            'business_id' => $biz->id,
            'event_type' => 'sent',
        ]);
    }

    public function test_d_helper_called_processes_send(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Gate Biz D', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $dnsAction = new EmailDnsCheckAction;
        $domain = $dnsAction->handle($biz->id, 'gate-d.apex-air.com');

        // with customerMailIsPermitted() called
        customerMailIsPermitted();

        $action = app(EmailSendAction::class);

        $result = $action->handle(
            businessId: $biz->id,
            mailDomainId: $domain->id,
            recipientEmail: 'test-d@acme.com',
            subject: 'New Mktg',
            sendType: 'marketing'
        );

        // the same send reaches 'processed'
        $this->assertSame('processed', $result['status']);

        // and writes its sent row
        $this->assertDatabaseHas('mail_events', [
            'business_id' => $biz->id,
            'event_type' => 'sent',
            'recipient_email' => 'test-d@acme.com',
        ]);
    }
}
