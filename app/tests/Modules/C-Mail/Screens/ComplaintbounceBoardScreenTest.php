<?php

declare(strict_types=1);

namespace Tests\Modules\CMail\Screens;

use App\Enums\MailEventType;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CMail\Models\MailDomain;
use App\Modules\CMail\Models\MailEvent;
use App\Modules\CMail\Ui\ComplaintbounceBoard;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ComplaintbounceBoardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);
        Tenancy::set((int) $biz->id);

        $this->get(route('c-mail.complaintbounce-board'))->assertOk();

        Livewire::test(ComplaintbounceBoard::class)->assertOk();
    }

    public function test_empty_state(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);
        Tenancy::set((int) $biz->id);

        $response = $this->get(route('c-mail.complaintbounce-board'));
        $response->assertOk();
        $response->assertSeeText('No mail events in the last 30 days.');

        $blade = file_get_contents(app_path('Modules/C-Mail/Ui/views/complaintbounce-board.blade.php'));
        $this->assertStringContainsString('No mail events in the last {{ $defaultDays }} days.', $blade);
        $this->assertMatchesRegularExpression('/@if\(count\(\$rows\) === 0\)[^@]+No mail events in the last \{\{ \$defaultDays \}\} days\./', $blade);
    }

    public function test_with_events_and_window(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);
        Tenancy::set((int) $biz->id);

        $domain = MailDomain::create([
            'business_id' => $biz->id,
            'domain_name' => 'test.com',
            'spf_status' => 'pending',
            'dkim_status' => 'pending',
            'dmarc_status' => 'pending',
        ]);

        Carbon::setTestNow(Carbon::now());

        MailEvent::create([
            'business_id' => $biz->id,
            'mail_domain_id' => $domain->id,
            'event_type' => MailEventType::Sent->value,
            'send_type' => 'transactional',
            'recipient_email' => 'a@b.com',
            'subject' => 'Test',
            'payload' => [],
            'created_at' => Carbon::now()->subDays(2),
        ]);

        MailEvent::create([
            'business_id' => $biz->id,
            'mail_domain_id' => $domain->id,
            'event_type' => MailEventType::Bounced->value,
            'send_type' => 'transactional',
            'recipient_email' => 'a@b.com',
            'subject' => 'Test',
            'payload' => [],
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $response = $this->get(route('c-mail.complaintbounce-board'));
        $response->assertSeeText('test.com');
        $response->assertSeeText('100%');

        Livewire::test(ComplaintbounceBoard::class)
            ->call('window', 7)
            ->assertSee('test.com')
            ->assertSee('0%');
    }

    public function test_another_tenant_events_invisible(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);

        $domainB = MailDomain::create([
            'business_id' => $bizB->id,
            'domain_name' => 'tenant-b.com',
            'spf_status' => 'pending',
            'dkim_status' => 'pending',
            'dmarc_status' => 'pending',
        ]);

        MailEvent::create([
            'business_id' => $bizB->id,
            'mail_domain_id' => $domainB->id,
            'event_type' => MailEventType::Sent->value,
            'send_type' => 'transactional',
            'recipient_email' => 'a@b.com',
            'subject' => 'Test',
            'payload' => [],
            'created_at' => Carbon::now(),
        ]);

        Tenancy::setUser($owner->id);
        Tenancy::set((int) $biz->id);
        $this->actingAs($owner);

        $response = $this->get(route('c-mail.complaintbounce-board'));
        $response->assertDontSeeText('tenant-b.com');
    }

    public function test_no_tenant_403(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner);
        // Not setting tenant explicitly, or clear it if needed.
        Tenancy::forget();

        $this->get(route('c-mail.complaintbounce-board'))->assertForbidden();
    }
}
