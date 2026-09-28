<?php

declare(strict_types=1);

namespace App\Tests\Modules\CMail;

use App\Modules\CMail\Actions\EmailSendAction;
use App\Modules\CMail\Models\MailDomain;
use App\Modules\X204\Domain\ConsentService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class G143Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        customerMailIsPermitted();
    }

    #[Test]
    #[Group('G1-43')]
    public function refuses_marketing_but_delivers_invoice_when_unsubscribed(): void
    {
        $business = self::provisionTenant();
        $businessId = $business->id;

        $domain = MailDomain::create([
            'business_id' => $businessId,
            'domain_name' => 'test.com',
            'is_marketing_paused' => false,
            'complaint_rate' => 0.0,
        ]);

        $consentService = new ConsentService;
        $recipientEmail = 'test@example.com';

        // Unsubscribe from marketing (add suppression)
        $consentService->suppress($businessId, $recipientEmail, 'email');

        $action = new EmailSendAction($consentService);

        // 1. Marketing is refused
        $marketingResult = $action->handle(
            businessId: $businessId,
            mailDomainId: $domain->id,
            recipientEmail: $recipientEmail,
            subject: 'Buy now',
            sendType: 'marketing'
        );
        $this->assertSame('refused_suppressed', $marketingResult['status']);

        // 2. Invoice is delivered
        $invoiceResult = $action->handle(
            businessId: $businessId,
            mailDomainId: $domain->id,
            recipientEmail: $recipientEmail,
            subject: 'Your Invoice',
            sendType: 'invoice'
        );
        $this->assertSame('processed', $invoiceResult['status']);
    }
}
