<?php

declare(strict_types=1);

namespace App\Modules\X182\Domain;

use App\Exceptions\GbpRequestFailed;
use App\Modules\X182\Models\SocialAccount;
use App\Modules\X182\Models\SocialPost;
use App\Services\Zernio\ZernioSocialClient;
use InvalidArgumentException;

final class SocialPublisher
{
    public function __construct(
        private readonly SocialPostRules $rules,
        private readonly ZernioSocialClient $client,
    ) {}

    /**
     * @return array<int, string>
     */
    public function refusalMessages(SocialAccount $account, string $content, ?string $imageUrl): array
    {
        if ($account->status !== 'connected' || $account->account_ref === null) {
            return ['Connect this account through Zernio first.'];
        }

        $codes = $this->rules->refusals([$account->platform], $content, $imageUrl === null ? [] : [$imageUrl]);
        $messages = [];
        foreach ($codes as $code) {
            $messages[] = match (true) {
                $code === 'content_empty' => 'Write something or add an image.',
                $code === 'instagram_needs_media' => 'Instagram needs an image or a video.',
                $code === 'instagram_caption_link' => 'Instagram does not allow links in the caption.',
                $code === 'media_not_https' => 'The image link must start with https://.',
                $code === 'media_type_unknown' => 'The image link must end in .jpg, .jpeg, .png, .gif, .webp, .mp4 or .mov.',
                default => "This post cannot be published ({$code}).",
            };
        }

        return $messages;
    }

    public function publish(SocialAccount $account, string $content, ?string $imageUrl): SocialPost
    {
        $messages = $this->refusalMessages($account, $content, $imageUrl);
        if (count($messages) > 0) {
            throw new InvalidArgumentException(implode(' ', $messages));
        }

        $post = SocialPost::create([
            'business_id' => $account->business_id,
            'account_id' => $account->id,
            'content_text' => $content,
            'image_url' => $imageUrl,
            'publish_status' => 'publishing',
            'is_published' => false,
        ]);

        try {
            $receipt = $this->client->publish(
                [['platform' => $account->platform, 'accountId' => $account->account_ref]],
                $content,
                $this->rules->mediaItems($imageUrl === null ? [] : [$imageUrl]),
                'social-post-'.$post->id,
                ['social_post_id' => (string) $post->id]
            );
        } catch (GbpRequestFailed $e) {
            $post->publish_status = 'failed';
            $post->last_error = 'Zernio refused it: '.$e->getMessage();
            $post->save();

            return $post;
        }

        $p = $receipt->platforms[$account->platform] ?? null;

        if ($receipt->outcome === 'published') {
            $post->publish_status = 'published';
            $post->is_published = true;
            $post->published_at = now();
            $post->provider_post_id = $receipt->providerPostId;
            $post->platform_post_url = $p !== null ? $p->platformPostUrl : null;
        } elseif ($receipt->outcome === 'partial' || $receipt->outcome === 'failed') {
            $post->publish_status = 'failed';
            $post->provider_post_id = $receipt->providerPostId;
            $post->last_error = ($p !== null && $p->errorMessage !== null) ? $p->errorMessage : 'Zernio could not publish it.';
        } elseif ($receipt->outcome === 'publishing' || $receipt->outcome === 'in_flight') {
            $post->publish_status = 'publishing';
            $post->provider_post_id = $receipt->providerPostId;
        } elseif ($receipt->outcome === 'unconfirmed') {
            $post->publish_status = 'unconfirmed';
            $post->provider_post_id = $receipt->providerPostId;
        } elseif ($receipt->outcome === 'duplicate') {
            $post->publish_status = 'duplicate';
            $post->provider_post_id = $receipt->existingPostId;
        }

        $post->save();

        return $post;
    }
}
