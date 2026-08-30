<?php

declare(strict_types=1);

namespace App\Modules\X182\Actions;

use App\Modules\X182\Events\PostPublished;
use App\Modules\X182\Models\SocialAccount;
use App\Modules\X182\Models\SocialPost;
use Illuminate\Support\Facades\Event;

final class PostPublishAction
{
    private ImageOverlayAction $overlayAction;

    public function __construct(?ImageOverlayAction $overlayAction = null)
    {
        $this->overlayAction = $overlayAction ?? new ImageOverlayAction;
    }

    /**
     * Publishes a social post (G2-08, G12-10).
     * Every social image has a branded overlay layer (TEST ANCHOR).
     */
    public function publishPost(
        int $businessId,
        int $accountId,
        string $contentText,
        ?string $rawImageUrl = null
    ): SocialPost {
        $account = SocialAccount::where('business_id', $businessId)->findOrFail($accountId);

        $overlayUrl = null;
        $hasOverlay = false;

        if ($rawImageUrl) {
            // TEST ANCHOR: Every social image has a branded overlay layer
            $overlayUrl = $this->overlayAction->applyOverlay($businessId, $rawImageUrl);
            $hasOverlay = true;
        }

        $post = SocialPost::create([
            'business_id' => $businessId,
            'account_id' => $account->id,
            'content_text' => $contentText,
            'image_url' => $rawImageUrl,
            'overlay_url' => $overlayUrl,
            'has_branded_overlay' => $hasOverlay,
            'is_published' => true,
        ]);

        Event::dispatch(new PostPublished($businessId, $post->id, $account->platform));

        return $post;
    }
}
