<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use App\Enums\UserRole;
use App\Models\ActivityFeedItem;
use App\Models\User;
use App\Modules\X102\Models\ChatLead;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X103\Models\SiteRecommendation;
use App\Modules\X108\Actions\WaitlistJoinAction;
use App\Modules\X121\Models\Person;
use App\Modules\X155\Models\FormDefinition;
use App\Modules\X155\Models\FormSubmission;
use App\Notifications\OwnerWeeklyDigest;
use App\Services\Activity\OwnerDigest;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class OwnerDigestSiteSectionTest extends TestCase
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
            'created_at' => now()->subDays(30),
        ]);
        $this->actingAs($this->owner);
        Tenancy::setUser($this->owner->id);
        Tenancy::set((int) $this->biz->id);

        Mail::fake();
        Notification::fake();
    }

    public function test_site_lines_count_only_rows_inside_the_window(): void
    {
        $page = Page::create(['business_id' => $this->biz->id, 'slug' => 'test', 'title' => 'Test']);
        PageVersion::create(['business_id' => $this->biz->id, 'page_id' => $page->id, 'commit_id' => 'abc', 'content_blocks' => '[]', 'created_at' => now()]);
        PageVersion::create(['business_id' => $this->biz->id, 'page_id' => $page->id, 'commit_id' => 'def', 'content_blocks' => '[]', 'created_at' => now()]);
        PageVersion::create(['business_id' => $this->biz->id, 'page_id' => $page->id, 'commit_id' => 'ghi', 'content_blocks' => '[]', 'created_at' => now()->subDays(40)]);

        $formDef = FormDefinition::create(['business_id' => $this->biz->id, 'form_name' => 'f', 'slug' => 'f', 'steps' => '[]', 'schema' => '[]']);
        $person = Person::create(['business_id' => $this->biz->id]);
        FormSubmission::create(['business_id' => $this->biz->id, 'form_definition_id' => $formDef->id, 'person_id' => $person->id, 'payload' => '[]', 'is_spam' => false, 'created_at' => now()]);

        app(WaitlistJoinAction::class)->handle($this->biz->id, 'John Doe', '1234567890', 'Service', now()->addDay()->format('Y-m-d'), false);

        $session = ChatSession::create(['business_id' => $this->biz->id, 'session_token' => 'token123']);
        ChatLead::create(['business_id' => $this->biz->id, 'chat_session_id' => $session->id, 'name' => 'Alice', 'phone' => '1112223333', 'created_at' => now()]);

        SiteRecommendation::create(['business_id' => $this->biz->id, 'code' => 'distinctive-code-4471', 'text' => 'pending', 'status' => 'pending', 'computed_at' => now()]);
        SiteRecommendation::create(['business_id' => $this->biz->id, 'code' => 'distinctive-code-4472', 'text' => 'dismissed', 'status' => 'dismissed', 'computed_at' => now()]);

        $c = app(OwnerDigest::class)->compose($this->biz);
        $this->assertNotNull($c);
        $this->assertContains('Your website: 2 pages published', $c['lines']);
        $this->assertContains('Your website: 1 form lead', $c['lines']);
        $this->assertContains('Your website: 1 booking request', $c['lines']);
        $this->assertContains('Your website: 1 chat lead', $c['lines']);
        $this->assertContains('Your website: 1 suggestion waiting for you', $c['lines']);
        $this->assertSame(0, $c['total']);
    }

    public function test_a_spam_submission_is_not_a_lead(): void
    {
        $formDef = FormDefinition::create(['business_id' => $this->biz->id, 'form_name' => 'f', 'slug' => 'f', 'steps' => '[]', 'schema' => '[]']);
        $person = Person::create(['business_id' => $this->biz->id]);
        FormSubmission::create(['business_id' => $this->biz->id, 'form_definition_id' => $formDef->id, 'person_id' => $person->id, 'payload' => '[]', 'is_spam' => true]);

        $this->assertNull(app(OwnerDigest::class)->compose($this->biz));
    }

    public function test_nothing_at_all_means_no_digest(): void
    {
        $this->assertNull(app(OwnerDigest::class)->compose($this->biz));
    }

    public function test_site_lines_follow_the_autopilot_lines_and_reach_the_mail(): void
    {
        ActivityFeedItem::factory()->create(['business_id' => $this->biz->id]);
        app(WaitlistJoinAction::class)->handle($this->biz->id, 'John Doe', '1234567890', 'Service', now()->addDay()->format('Y-m-d'), false);

        $c = app(OwnerDigest::class)->compose($this->biz);
        $this->assertStringEndsWith('1 booking request', end($c['lines']));

        $mail = (new OwnerWeeklyDigest(businessName: 'Distinctive Biz 4471', since: $c['since'], lines: $c['lines'], overflow: $c['overflow'], activityUrl: 'https://example.test/activity'))->toMail($this->owner);

        $this->assertContains('Your website: 1 booking request', $mail->introLines);
    }
}
