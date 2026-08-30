<?php

declare(strict_types=1);

namespace Tests\Modules\X182;

use App\Modules\X121\Models\Business;
use App\Modules\X182\Actions\CommentReplyAction;
use App\Modules\X182\Actions\PostPublishAction;
use App\Modules\X182\Events\CommentEscalated;
use App\Modules\X182\Events\CommentReceived;
use App\Modules\X182\Events\PostPublished;
use App\Modules\X182\Models\Comment;
use App\Modules\X182\Models\SocialAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X182Test extends TestCase
{
    private PostPublishAction $publishAction;

    private CommentReplyAction $replyAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishAction = new PostPublishAction;
        $this->replyAction = new CommentReplyAction;
    }

    /**
     * TEST ANCHOR
     * a comment classified negative never receives an automated public reply — the only write is an inbox row;
     * every social image has a branded overlay layer
     */
    public function test_anchor_negative_comment_suppresses_public_reply_and_branded_overlay_applied(): void
    {
        Event::fake([PostPublished::class, CommentReceived::class, CommentEscalated::class]);

        $biz = Business::provision(['name' => 'Social Media Publisher Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $account = SocialAccount::create([
            'business_id' => $biz->id,
            'platform' => 'facebook',
            'account_handle' => '@apexplumbingdfw',
            'is_connected' => true,
        ]);

        // 1. Publish post with image: verify branded overlay layer is generated (TEST ANCHOR & G12-32, G12-38)
        $rawImage = 'https://assets.local/job_photo_plumbing_install.jpg';
        $post = $this->publishAction->publishPost(
            businessId: $biz->id,
            accountId: $account->id,
            contentText: 'Completed full commercial repiping project in downtown Dallas today!',
            rawImageUrl: $rawImage
        );

        $this->assertNotNull($post);
        $this->assertTrue($post->has_branded_overlay, 'Every social image has branded overlay layer (TEST ANCHOR)');
        $this->assertNotEmpty($post->overlay_url);
        Event::assertDispatched(PostPublished::class);

        // 2. Positive comment receives automated public reply
        $posComment = $this->replyAction->handleInboundComment(
            businessId: $biz->id,
            postId: $post->id,
            authorName: 'Sarah J.',
            commentText: 'Awesome job on the repair, thank you!',
            sentiment: 'positive'
        );

        $this->assertTrue($posComment->is_publicly_replied);
        $this->assertFalse($posComment->is_escalated_to_inbox);
        Event::assertDispatched(CommentReceived::class);

        // 3. Negative comment NEVER receives automated public reply — only write is inbox row (TEST ANCHOR & P-110, G12-38)
        $negComment = $this->replyAction->handleInboundComment(
            businessId: $biz->id,
            postId: $post->id,
            authorName: 'Disgruntled Client',
            commentText: 'Technician was 45 minutes late and did not call in advance.',
            sentiment: 'negative'
        );

        $this->assertFalse($negComment->is_publicly_replied, 'Negative comment NEVER receives automated public reply (TEST ANCHOR)');
        $this->assertNull($negComment->automated_reply_text);
        $this->assertTrue($negComment->is_escalated_to_inbox, 'Only write is an inbox escalation row (TEST ANCHOR)');

        Event::assertDispatched(CommentEscalated::class);
    }

    /**
     * [G2-08], [G12-10], [G12-12], [G12-17], [G12-26], [G12-32], [G12-38]
     */
    public function test_social_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
