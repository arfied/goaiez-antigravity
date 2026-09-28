<?php

declare(strict_types=1);

namespace App\Modules\X177\Actions;

use App\Modules\X177\Events\GbpPosted;
use App\Modules\X177\Models\GbpPost;

final class GbpPostSettleAction
{
    public function settle(int $businessId, int $gbpPostId, bool $published, ?string $reason = null, ?string $providerPostId = null): string
    {
        $post = GbpPost::where('business_id', $businessId)->find($gbpPostId);

        if ($post === null) {
            return 'unknown_post';
        }

        if ($post->status === 'posted' || $post->status === 'failed') {
            return 'already_settled';
        }

        if ($published) {
            $post->status = 'posted';
            if ($post->zernio_dispatch_id === null && $providerPostId !== null) {
                $post->zernio_dispatch_id = $providerPostId;
            }
            $post->save();

            event(new GbpPosted($businessId, $post->id, $post->zernio_dispatch_id));
        } else {
            $post->status = 'failed';
            $post->failure_reason = $reason ?? 'Google did not accept the post.';
            $post->save();
        }

        return 'settled';
    }
}
