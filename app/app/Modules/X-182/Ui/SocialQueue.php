<?php

declare(strict_types=1);

namespace App\Modules\X182\Ui;

use App\Exceptions\GbpRequestFailed;
use App\Modules\X182\Actions\CommentIngestAction;
use App\Modules\X182\Actions\SocialPostSettleAction;
use App\Modules\X182\Domain\SocialPublisher;
use App\Modules\X182\Models\Comment;
use App\Modules\X182\Models\SocialAccount;
use App\Modules\X182\Models\SocialPost;
use App\Services\Conversations\ConversationThreads;
use App\Services\Zernio\ZernioSocialClient;
use App\Support\Tenancy;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

#[Layout('components.account.layout', ['heading' => 'Social queue'])]
class SocialQueue extends Component
{
    #[Locked]
    public int $businessId = 0;

    public ?int $accountId = null;

    public string $content = '';

    public string $imageUrl = '';

    public array $commentReply = [];

    public array $privateReply = [];

    public array $dmReply = [];

    #[Locked]
    public string $dmDraftKey = '';

    public function mount(int $businessId = 0): void
    {
        $this->dmDraftKey = (string) Str::uuid();
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
        abort_if($this->businessId === 0, 404);
        $topic = request()->query('topic');
        if (is_string($topic) && trim($topic) !== '') {
            $this->content = mb_substr(trim($topic), 0, 500);
        }
    }

    public function publish(SocialPublisher $publisher): void
    {
        abort_unless(auth()->user()?->role->canConfigureAutomation() === true, 403);

        $account = SocialAccount::where('business_id', $this->businessId)->find($this->accountId);
        if ($account === null) {
            Toaster::error('Choose an account to publish to.');

            return;
        }

        $messages = $publisher->refusalMessages($account, $this->content, $this->imageUrl !== '' ? $this->imageUrl : null);
        if (count($messages) > 0) {
            Toaster::error(implode(' ', $messages));

            return;
        }

        $post = $publisher->publish($account, $this->content, $this->imageUrl !== '' ? $this->imageUrl : null);

        if ($post->publish_status === 'published') {
            Toaster::success('Published on '.ucfirst($account->platform).'.');
        } elseif ($post->publish_status === 'failed') {
            Toaster::error('Not published — '.$post->last_error);
        } elseif ($post->publish_status === 'duplicate') {
            Toaster::info('Zernio already has this post.');
        } else {
            Toaster::info('Sent to Zernio. It has not confirmed the post yet.');
        }

        $this->reset('content', 'imageUrl');
    }

    public function checkAgain(int $postId): void
    {
        abort_unless(auth()->user()?->role->canConfigureAutomation() === true, 403);

        $post = SocialPost::where('business_id', $this->businessId)
            ->whereIn('publish_status', ['publishing', 'unconfirmed'])
            ->with('account')
            ->find($postId);

        if ($post === null) {
            Toaster::info('Nothing to check — that post is not waiting on Zernio.');

            return;
        }

        if ($post->provider_post_id === null) {
            Toaster::error('That post never reached Zernio, so there is nothing to check.');

            return;
        }

        $client = app(ZernioSocialClient::class);

        try {
            $receipt = $client->getPost($post->provider_post_id);
        } catch (GbpRequestFailed) {
            Toaster::error('We could not reach Zernio. Try again shortly.');

            return;
        }

        $p = $receipt->platforms[$post->account->platform] ?? null;

        if ($receipt->outcome === 'published') {
            app(SocialPostSettleAction::class)->settle($this->businessId, $post->id, (string) $post->account->account_ref, true, $p?->platformPostUrl, null, $receipt->providerPostId);
            Toaster::success('Published on '.ucfirst($post->account->platform).'.');
        } elseif ($receipt->outcome === 'failed' || $receipt->outcome === 'partial') {
            app(SocialPostSettleAction::class)->settle($this->businessId, $post->id, (string) $post->account->account_ref, false, null, $p?->errorMessage, $receipt->providerPostId);
            $err = $p !== null && $p->errorMessage !== null ? $p->errorMessage : 'Zernio could not publish it.';
            Toaster::error('Not published — '.$err);
        } else {
            Toaster::info('Zernio is still publishing it.');
        }
    }

    public function replyToComment(int $commentId): void
    {
        abort_unless(auth()->user()?->role->canConfigureAutomation() === true, 403);

        $comment = Comment::where('business_id', $this->businessId)->find($commentId);
        if ($comment === null) {
            Toaster::error('That comment is not here any more.');

            return;
        }

        if ($comment->replied_at !== null) {
            Toaster::info('You already replied to this comment.');

            return;
        }

        if ($comment->platform_comment_id === null) {
            Toaster::error('This comment cannot be answered from here.');

            return;
        }

        $post = SocialPost::where('business_id', $this->businessId)->with('account')->find($comment->post_id);
        if ($post === null || $post->provider_post_id === null || $post->account === null || $post->account->status !== 'connected' || $post->account->account_ref === null) {
            Toaster::error('Connect this account through Zernio first.');

            return;
        }

        $text = trim((string) ($this->commentReply[$commentId] ?? ''));
        if ($text === '' || mb_strlen($text) > 1000) {
            Toaster::error('Write a reply of up to 1,000 characters.');

            return;
        }

        try {
            $ref = app(ZernioSocialClient::class)->replyToComment(
                $post->account->account_ref,
                $post->provider_post_id,
                $comment->platform_comment_id,
                $text,
                'comment-reply-'.$comment->id
            );
        } catch (GbpRequestFailed $e) {
            Toaster::error('Zernio did not accept the reply: '.$e->getMessage());

            return;
        }

        app(CommentIngestAction::class)->recordOwnerReply($comment, $text, $ref);
        Toaster::success('Replied.');
        unset($this->commentReply[$commentId]);
    }

    public function hideComment(int $commentId): void
    {
        abort_unless(auth()->user()?->role->canConfigureAutomation() === true, 403);

        $comment = Comment::where('business_id', $this->businessId)->find($commentId);
        if ($comment === null) {
            Toaster::error('That comment is not here any more.');

            return;
        }

        if ($comment->platform_comment_id === null) {
            Toaster::error('This comment cannot be hidden from here.');

            return;
        }

        $post = SocialPost::where('business_id', $this->businessId)->with('account')->find($comment->post_id);
        if ($post === null || $post->provider_post_id === null || $post->account === null || $post->account->status !== 'connected' || $post->account->account_ref === null) {
            Toaster::error('Connect this account through Zernio first.');

            return;
        }

        if ($comment->hidden_at !== null) {
            Toaster::info('This comment is already hidden.');

            return;
        }

        try {
            app(ZernioSocialClient::class)->hideComment($post->account->account_ref, $post->provider_post_id, $comment->platform_comment_id);
        } catch (GbpRequestFailed $e) {
            Toaster::error('Zernio did not hide it: '.$e->getMessage());

            return;
        }

        $comment->update(['hidden_at' => now()]);
        Toaster::success('Hidden. Only the commenter and your page can see it now.');
    }

    public function unhideComment(int $commentId): void
    {
        abort_unless(auth()->user()?->role->canConfigureAutomation() === true, 403);

        $comment = Comment::where('business_id', $this->businessId)->find($commentId);
        if ($comment === null) {
            Toaster::error('That comment is not here any more.');

            return;
        }

        if ($comment->platform_comment_id === null) {
            Toaster::error('This comment cannot be hidden from here.');

            return;
        }

        $post = SocialPost::where('business_id', $this->businessId)->with('account')->find($comment->post_id);
        if ($post === null || $post->provider_post_id === null || $post->account === null || $post->account->status !== 'connected' || $post->account->account_ref === null) {
            Toaster::error('Connect this account through Zernio first.');

            return;
        }

        if ($comment->hidden_at === null) {
            Toaster::info('This comment is not hidden.');

            return;
        }

        try {
            app(ZernioSocialClient::class)->unhideComment($post->account->account_ref, $post->provider_post_id, $comment->platform_comment_id);
        } catch (GbpRequestFailed $e) {
            Toaster::error('Zernio did not unhide it: '.$e->getMessage());

            return;
        }

        $comment->update(['hidden_at' => null]);
        Toaster::success('Shown again to everyone.');
    }

    public function privateReplyToComment(int $commentId): void
    {
        abort_unless(auth()->user()?->role->canConfigureAutomation() === true, 403);

        $comment = Comment::where('business_id', $this->businessId)->find($commentId);
        if ($comment === null) {
            Toaster::error('That comment is not here any more.');

            return;
        }

        if ($comment->private_replied_at !== null) {
            Toaster::info('You already sent this person a private message.');

            return;
        }

        if (! app(CommentIngestAction::class)->canReplyPrivately($comment)) {
            Toaster::error('Facebook and Instagram allow one private message within 7 days of a comment, and this comment cannot get one.');

            return;
        }

        $post = SocialPost::where('business_id', $this->businessId)->with('account')->find($comment->post_id);
        if ($post === null || $post->provider_post_id === null || $post->account === null || $post->account->status !== 'connected' || $post->account->account_ref === null || ! in_array($post->account->platform, ['facebook', 'instagram'], true)) {
            Toaster::error('Connect this account through Zernio first.');

            return;
        }

        $text = trim((string) ($this->privateReply[$commentId] ?? ''));
        if ($text === '' || mb_strlen($text) > 1000) {
            Toaster::error('Write a message of up to 1,000 characters.');

            return;
        }

        try {
            app(ZernioSocialClient::class)->privateReplyToComment(
                $post->account->account_ref,
                $comment->platform_post_id,
                $comment->platform_comment_id,
                $text,
                'comment-private-reply-'.$comment->id
            );
        } catch (GbpRequestFailed $e) {
            Toaster::error('Zernio did not send it: '.$e->getMessage());

            return;
        }

        app(CommentIngestAction::class)->recordPrivateReply($comment, $text);
        Toaster::success('Sent as a private message.');
        unset($this->privateReply[$commentId]);
    }

    public function replyToDm(int $conversationId): void
    {
        abort_unless(auth()->user()?->role->canConfigureAutomation() === true, 403);

        $thread = app(ConversationThreads::class)->findSocial($conversationId);
        if ($thread === null) {
            Toaster::error('That conversation is not here any more.');

            return;
        }

        if ($thread->provider_conversation_ref === null || $thread->provider_account_ref === null) {
            Toaster::error('This conversation cannot be answered from here.');

            return;
        }

        $text = trim((string) ($this->dmReply[$conversationId] ?? ''));
        if ($text === '' || mb_strlen($text) > 1000) {
            Toaster::error('Write a reply of up to 1,000 characters.');

            return;
        }

        try {
            app(ZernioSocialClient::class)->replyInConversation(
                $thread->provider_account_ref,
                $thread->provider_conversation_ref,
                $text,
                'dm-reply-'.$thread->id.'-'.$this->dmDraftKey
            );
        } catch (GbpRequestFailed $e) {
            Toaster::error(ucfirst($thread->channel).' did not accept the reply: '.$e->getMessage());

            return;
        }

        app(ConversationThreads::class)->recordOutboundFromPerson(
            conversation: $thread,
            body: $text,
            userId: (int) auth()->id()
        );

        Toaster::success('Sent on '.ucfirst($thread->channel).'.');
        unset($this->dmReply[$conversationId]);
        $this->dmDraftKey = (string) Str::uuid();
    }

    public function render()
    {
        $posts = ($this->businessId > 0)
            ? SocialPost::where('business_id', $this->businessId)->with('account', 'comments')->orderByDesc('id')->get()
            : collect();

        $accounts = ($this->businessId > 0)
            ? SocialAccount::where('business_id', $this->businessId)->where('status', 'connected')->whereNotNull('account_ref')->orderBy('platform')->get()
            : collect();

        $dmThreads = ($this->businessId > 0)
            ? app(ConversationThreads::class)->socialThreads()
            : collect();

        $dmTails = [];
        foreach ($dmThreads as $t) {
            $dmTails[$t->id] = app(ConversationThreads::class)->tail($t, 5);
        }

        return view('x-182::social-queue', [
            'posts' => $posts,
            'accounts' => $accounts,
            'dmThreads' => $dmThreads,
            'dmTails' => $dmTails,
        ]);
    }
}
