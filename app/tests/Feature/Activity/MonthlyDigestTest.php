<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use App\Enums\UserRole;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X108\Actions\WaitlistJoinAction;
use App\Services\Activity\MonthlyDigest;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class MonthlyDigestTest extends TestCase
{
    use RefreshesTenantDatabase;

    private User $owner;

    private $biz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->biz = TestCase::provisionTenant([
            'owner_user_id' => $this->owner->id,
            'created_at' => now()->subDays(60),
        ]);
        $this->actingAs($this->owner);
        Tenancy::setUser($this->owner->id);
        Tenancy::set((int) $this->biz->id);
    }

    public function test_window_with_no_cursor_is_previous_calendar_month(): void
    {
        $digest = app(MonthlyDigest::class);
        $w = $digest->window($this->biz);

        $expectedFrom = Carbon::now()->subMonthNoOverflow()->startOfMonth();

        $this->assertTrue($w['from']->equalTo($expectedFrom));
        $this->assertSame($expectedFrom->translatedFormat('F Y'), $w['label']);
    }

    public function test_window_with_cursor_is_since_that_date(): void
    {
        $cursor = now()->subDays(10)->startOfSecond();
        $this->biz->owner_monthly_digest_sent_at = $cursor;

        $digest = app(MonthlyDigest::class);
        $w = $digest->window($this->biz);

        $this->assertEquals($cursor->timestamp, $w['from']->timestamp);
        $this->assertSame('since '.$cursor->translatedFormat('j F'), $w['label']);
    }

    public function test_compose_formats_pages_and_excludes_lines(): void
    {
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();

        $page1 = Page::create(['business_id' => $this->biz->id, 'slug' => 'test-1', 'title' => 'Distinctive page 4471']);
        $page2 = Page::create(['business_id' => $this->biz->id, 'slug' => 'test-2', 'title' => 'Distinctive page 4472']);

        // inside last month
        PageVersion::create(['business_id' => $this->biz->id, 'page_id' => $page1->id, 'commit_id' => 'abc', 'content_blocks' => '[]', 'created_at' => $lastMonthStart->copy()->addDays(3)]);
        PageVersion::create(['business_id' => $this->biz->id, 'page_id' => $page1->id, 'commit_id' => 'def', 'content_blocks' => '[]', 'created_at' => $lastMonthStart->copy()->addDays(4)]);
        PageVersion::create(['business_id' => $this->biz->id, 'page_id' => $page2->id, 'commit_id' => 'ghi', 'content_blocks' => '[]', 'created_at' => $lastMonthStart->copy()->addDays(2)]);

        // outside (this month)
        PageVersion::create(['business_id' => $this->biz->id, 'page_id' => $page2->id, 'commit_id' => 'jkl', 'content_blocks' => '[]', 'created_at' => now()]);

        // a waitlist row inside the window
        Carbon::setTestNow($lastMonthStart->copy()->addDays(5));
        app(WaitlistJoinAction::class)->handle($this->biz->id, 'John Doe', '1234567890', 'Service', now()->addDay()->format('Y-m-d'), false);
        Carbon::setTestNow();

        $digest = app(MonthlyDigest::class);
        $content = $digest->compose($this->biz);

        $this->assertNotNull($content);
        $this->assertNull($content['visits']);
        $this->assertSame([
            ['title' => 'Distinctive page 4471', 'times' => 2],
            ['title' => 'Distinctive page 4472', 'times' => 1],
        ], $content['pages']);

        $this->assertContains('Your website: 1 booking request', $content['lines']);
        // Verify 'pages published' was excluded
        foreach ($content['lines'] as $line) {
            $this->assertStringNotContainsString('published', $line);
        }
    }

    public function test_nothing_in_window_returns_null(): void
    {
        $this->assertNull(app(MonthlyDigest::class)->compose($this->biz));
    }

    public function test_eligibility_rules(): void
    {
        $digest = app(MonthlyDigest::class);

        // Created 10 days ago -> false
        $this->biz->created_at = now()->subDays(10);
        $this->assertFalse($digest->eligible($this->biz));

        // Sent 5 days ago -> false
        $this->biz->created_at = now()->subDays(60);
        $this->biz->owner_monthly_digest_sent_at = now()->subDays(5);
        $this->assertFalse($digest->eligible($this->biz));

        // Sent 40 days ago -> true (if entitled)
        $this->biz->owner_monthly_digest_sent_at = now()->subDays(40);

        // Delete the pending_checkout subscription so the business defaults to entitled
        Subscription::where('business_id', $this->biz->id)->delete();
        $this->assertTrue(app(MonthlyDigest::class)->eligible($this->biz));
    }
}
