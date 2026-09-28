<?php

declare(strict_types=1);

namespace App\Modules\X182\Actions;

use App\Modules\X182\Models\SocialPost;

final class SocialPostSettleAction
{
    public function settle(
        int $businessId,
        int $socialPostId,
        string $accountRef,
        bool $published,
        ?string $publishedUrl,
        ?string $error,
        ?string $providerPostId
    ): string {
        $post = SocialPost::where('business_id', $businessId)->with('account')->find($socialPostId);

        if (! $post || $post->account?->account_ref !== $accountRef) {
            return 'unknown_post';
        }

        if (in_array($post->publish_status, ['published', 'failed'], true)) {
            return 'already_settled';
        }

        if ($published) {
            $post->publish_status = 'published';
            $post->is_published = true;
            $post->published_at = now();
            $post->platform_post_url = $publishedUrl ?? $post->platform_post_url;

            if ($post->provider_post_id === null) {
                $post->provider_post_id = $providerPostId;
            }
        } else {
            $post->publish_status = 'failed';
            $post->last_error = $error ?? 'Zernio could not publish it.';
        }

        $post->save();

        return 'settled';
    }
}
