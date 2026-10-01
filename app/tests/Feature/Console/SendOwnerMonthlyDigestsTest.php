<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Notifications\OwnerMonthlyDigest;
use App\Services\Config\DefaultsRegistry;
use App\Services\Mail\PlatformMailer;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class SendOwnerMonthlyDigestsTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_switch_off_means_nothing_sent_and_cursor_not_moved(): void
    {
        Notification::fake();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant([
            'owner_user_id' => $owner->id,
            'created_at' => now()->subDays(60),
            'owner_monthly_digest_sent_at' => null,
        ]);

        app(DefaultsRegistry::class)->set('owner_digest.monthly_enabled', false, 'test');

        $this->artisan('owners:send-monthly-site-digest')->assertSuccessful();

        Notification::assertNothingSent();
        Tenancy::set((int) $biz->id);
        $this->assertNull($biz->fresh()->owner_monthly_digest_sent_at);
    }

    public function test_switch_on_sends_or_skips_depending_on_can_deliver(): void
    {
        Notification::fake();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant([
            'owner_user_id' => $owner->id,
            'created_at' => now()->subDays(60),
            'owner_monthly_digest_sent_at' => null,
        ]);
        Subscription::where('business_id', $biz->id)->delete();
        // provisionTenant() persists neither created_at nor the cursor (tests/TestCase.php:177-200),
        // and the command re-reads the row, so the age must be in the database, not on the model.
        Business::query()->whereKey($biz->id)->update(['created_at' => now()->subDays(60)]);

        $page = Page::create(['business_id' => $biz->id, 'slug' => 'test-1', 'title' => 'Distinctive page 4471']);
        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => 'abc',
            'content_blocks' => '[]',
            'created_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(3),
        ]);

        $this->artisan('owners:send-monthly-site-digest')->assertSuccessful();

        Tenancy::set((int) $biz->id);
        $mailer = app(PlatformMailer::class);
        if ($mailer->canDeliver()) {
            Notification::assertSentOnDemand(OwnerMonthlyDigest::class);
            $this->assertNotNull($biz->fresh()->owner_monthly_digest_sent_at);
        } else {
            Notification::assertNothingSent();
            $this->assertNull($biz->fresh()->owner_monthly_digest_sent_at);
        }
    }

    public function test_to_mail_shape(): void
    {
        $notification = new OwnerMonthlyDigest(
            businessName: 'My Biz',
            label: 'F Y',
            pages: [['title' => 'Distinctive page 4471', 'times' => 2]],
            visits: null,
            lines: [],
            activityUrl: 'https://example.test'
        );

        $mail = $notification->toMail(new class {});

        $this->assertContains('Published: Distinctive page 4471 (2 times)', $mail->introLines);
        $this->assertContains('Visits to your site: not measured yet — the page has not sent us a visit', $mail->introLines);
        $this->assertStringContainsString('My Biz', (string) $mail->subject);

        $notification2 = new OwnerMonthlyDigest(
            businessName: 'My Biz',
            label: 'F Y',
            pages: [['title' => 'Distinctive page 4471', 'times' => 2]],
            visits: 40,
            lines: [],
            activityUrl: 'https://example.test'
        );

        $mail2 = $notification2->toMail(new class {});

        $this->assertContains('Visits to your site: 40', $mail2->introLines);
        $this->assertStringNotContainsString('not measured yet', implode(' ', $mail2->introLines));
    }

    public function test_the_cursor_lands_on_the_window_end_so_no_days_are_lost(): void
    {
        mailerCanDeliver();

        Notification::fake();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant([
            'owner_user_id' => $owner->id,
            'created_at' => now()->subDays(60),
            'owner_monthly_digest_sent_at' => null,
        ]);
        Subscription::where('business_id', $biz->id)->delete();
        // provisionTenant() persists neither created_at nor the cursor (tests/TestCase.php:177-200),
        // and the command re-reads the row, so the age must be in the database, not on the model.
        Business::query()->whereKey($biz->id)->update(['created_at' => now()->subDays(60)]);

        $page1 = Page::create(['business_id' => $biz->id, 'slug' => 'test-1', 'title' => 'Distinctive page 4471']);
        $page2 = Page::create(['business_id' => $biz->id, 'slug' => 'test-2', 'title' => 'Distinctive page 4721']);

        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page1->id,
            'commit_id' => 'abc',
            'content_blocks' => '[]',
            'created_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(3),
        ]);

        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page2->id,
            'commit_id' => 'def',
            'content_blocks' => '[]',
            'created_at' => now(),
        ]);

        $this->artisan('owners:send-monthly-site-digest')->assertSuccessful();

        Tenancy::set((int) $biz->id);
        $firstTo = now()->startOfMonth();
        $this->assertTrue($biz->fresh()->owner_monthly_digest_sent_at->equalTo($firstTo));

        $this->travel(29)->days();

        Notification::fake(); // reset

        $this->artisan('owners:send-monthly-site-digest')->assertSuccessful();

        Notification::assertSentOnDemand(OwnerMonthlyDigest::class, function ($notification) {
            $mail = $notification->toMail(new class {});

            return collect($mail->introLines)->contains(fn ($line) => str_contains((string) $line, 'Distinctive page 4721'));
        });
    }
}
