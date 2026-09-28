<?php

declare(strict_types=1);

namespace App\Modules\X182\Domain;

final class SocialPostRules
{
    /**
     * @param  array<string>  $platforms
     * @param  array<string>  $mediaUrls
     * @return array<string>
     */
    public function refusals(array $platforms, string $content, array $mediaUrls): array
    {
        $refusals = [];

        $hasMedia = count($mediaUrls) > 0;
        if (trim($content) === '' && ! $hasMedia) {
            $refusals[] = 'content_empty';
        }

        foreach ($platforms as $p) {
            if ($p !== 'facebook' && $p !== 'instagram') {
                $refusals[] = "platform_unknown:{$p}";
            }
        }

        $hasInstagram = in_array('instagram', $platforms, true);
        if ($hasInstagram && ! $hasMedia) {
            $refusals[] = 'instagram_needs_media';
        }

        if ($hasInstagram && preg_match('~https?://|www\.~i', $content)) {
            $refusals[] = 'instagram_caption_link';
        }

        $hasNotHttps = false;
        $hasUnknownType = false;

        foreach ($mediaUrls as $url) {
            if (! str_starts_with($url, 'https://')) {
                $hasNotHttps = true;
            }
            $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov'], true)) {
                $hasUnknownType = true;
            }
        }

        if ($hasNotHttps) {
            $refusals[] = 'media_not_https';
        }

        if ($hasUnknownType) {
            $refusals[] = 'media_type_unknown';
        }

        $mediaItems = $this->mediaItems($mediaUrls);
        $imagesCount = 0;
        $videosCount = 0;

        foreach ($mediaItems as $item) {
            if ($item['type'] === 'image') {
                $imagesCount++;
            } elseif ($item['type'] === 'video') {
                $videosCount++;
            }
        }

        $hasFacebook = in_array('facebook', $platforms, true);
        if ($hasFacebook) {
            if ($imagesCount > 10) {
                $refusals[] = 'facebook_too_many_images';
            }
            if ($videosCount > 1) {
                $refusals[] = 'facebook_one_video_only';
            }
            if ($imagesCount > 0 && $videosCount > 0) {
                $refusals[] = 'facebook_no_mix';
            }
        }

        if ($hasInstagram && count($mediaUrls) > 10) {
            // OUR cap, because Zernio's docs do not state it
            $refusals[] = 'instagram_too_many_media';
        }

        return $refusals;
    }

    /**
     * @param  array<string>  $mediaUrls
     * @return array<int, array{type: string, url: string}>
     */
    public function mediaItems(array $mediaUrls): array
    {
        $items = [];
        foreach ($mediaUrls as $url) {
            $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $items[] = ['type' => 'image', 'url' => $url];
            } elseif (in_array($ext, ['mp4', 'mov'], true)) {
                $items[] = ['type' => 'video', 'url' => $url];
            } else {
                $items[] = ['type' => 'unknown', 'url' => $url];
            }
        }

        return $items;
    }
}
