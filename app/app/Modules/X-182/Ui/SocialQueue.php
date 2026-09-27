<?php

declare(strict_types=1);

namespace App\Modules\X182\Ui;

use App\Exceptions\GbpRequestFailed;
use App\Modules\X182\Actions\SocialPostSettleAction;
use App\Modules\X182\Domain\SocialPublisher;
use App\Modules\X182\Models\SocialAccount;
use App\Modules\X182\Models\SocialPost;
use App\Services\Zernio\ZernioSocialClient;
use App\Support\Tenancy;
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

    public function mount(int $businessId = 0): void
    {
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

    public function render()
    {
        $posts = ($this->businessId > 0)
            ? SocialPost::where('business_id', $this->businessId)->with('account', 'comments')->orderByDesc('id')->get()
            : collect();

        $accounts = ($this->businessId > 0)
            ? SocialAccount::where('business_id', $this->businessId)->where('status', 'connected')->whereNotNull('account_ref')->orderBy('platform')->get()
            : collect();

        return view('x-182::social-queue', [
            'posts' => $posts,
            'accounts' => $accounts,
        ]);
    }
}
