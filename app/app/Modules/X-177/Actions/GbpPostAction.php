<?php

declare(strict_types=1);

namespace App\Modules\X177\Actions;

use App\Exceptions\GbpRequestFailed;
use App\Modules\X177\Events\GbpPosted;
use App\Modules\X177\Events\GbpSuspensionRisk;
use App\Modules\X177\Models\GbpConnection;
use App\Modules\X177\Models\GbpPost;
use App\Services\Gbp\ZernioGbpClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class GbpPostAction
{
    private const array RISK_KEYWORDS = [
        'guaranteed ranking #1',
        'click here for free crypto',
        'wire transfer to claim prize',
    ];

    private const array CTA_TYPES = ['LEARN_MORE', 'BOOK', 'ORDER', 'SHOP', 'SIGN_UP', 'CALL'];

    public function __construct(private readonly ?ZernioGbpClient $client = null) {}

    /**
     * Posts update to GBP via Zernio.
     * 1. On gbp.suspended ZERO posts are attempted for that profile until gbp.reinstated (TEST ANCHOR).
     * 2. A write flagged by the risk ruleset never reaches Zernio (TEST ANCHOR).
     */
    public function post(
        int $businessId,
        int $connectionId,
        string $content,
        string $postType = 'update',
        ?string $imageUrl = null,
        ?string $ctaType = null,
        ?string $ctaUrl = null
    ): array {
        $conn = GbpConnection::where('business_id', $businessId)->findOrFail($connectionId);

        // 1. Suspension gate (TEST ANCHOR)
        if ($conn->profile_status === 'suspended') {
            $post = GbpPost::create([
                'business_id' => $businessId,
                'connection_id' => $conn->id,
                'post_type' => $postType,
                'content' => $content,
                'status' => 'blocked_by_suspension',
                'zernio_dispatch_id' => null, // Never reaches Zernio (TEST ANCHOR)
            ]);

            return [
                'status' => 'blocked',
                'refusal_code' => 'PROFILE_SUSPENDED',
                'message' => 'Zero posts attempted while GBP profile is suspended',
                'post_id' => $post->id,
                'dispatched_to_zernio' => false,
            ];
        }

        // 2. Risk ruleset gate (TEST ANCHOR)
        foreach (self::RISK_KEYWORDS as $risk) {
            if (str_contains(strtolower($content), $risk)) {
                $post = GbpPost::create([
                    'business_id' => $businessId,
                    'connection_id' => $conn->id,
                    'post_type' => $postType,
                    'content' => $content,
                    'status' => 'rejected_risk',
                    'zernio_dispatch_id' => null, // Never reaches Zernio (TEST ANCHOR)
                ]);

                Event::dispatch(new GbpSuspensionRisk($businessId, $conn->id, "Contains risk phrase: '{$risk}'"));

                return [
                    'status' => 'refused_risk',
                    'refusal_code' => 'SUSPENSION_RISK_FLAGGED',
                    'message' => "Write flagged by risk ruleset ('{$risk}') and never reaches Zernio",
                    'post_id' => $post->id,
                    'dispatched_to_zernio' => false,
                ];
            }
        }

        if ($imageUrl !== null) {
            $parsed = parse_url($imageUrl);
            $path = $parsed['path'] ?? '';
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (! str_starts_with(strtolower($imageUrl), 'https://') || ! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                return [
                    'status' => 'refused_image',
                    'message' => 'Use a public https link to a JPEG or PNG image.',
                    'dispatched_to_zernio' => false,
                    'zernio_dispatch_id' => null,
                ];
            }
        }

        if ($ctaType !== null || $ctaUrl !== null) {
            if ($ctaType === null || $ctaUrl === null || ! in_array($ctaType, self::CTA_TYPES, true) || ! str_starts_with($ctaUrl, 'https://')) {
                return [
                    'status' => 'refused_cta',
                    'message' => 'Pick a button and give it an https link.',
                    'dispatched_to_zernio' => false,
                    'zernio_dispatch_id' => null,
                ];
            }
        }

        // 3. Valid post dispatch to Zernio (TEST ANCHOR)
        $connRow = DB::table('gbp_connections')->where('id', $conn->id)->first(['account_ref', 'status']);

        if (empty($connRow->account_ref) || $connRow->status !== 'connected') {
            $post = GbpPost::create([
                'business_id' => $businessId,
                'connection_id' => $conn->id,
                'post_type' => $postType,
                'content' => $content,
                'status' => 'not_connected',
                'zernio_dispatch_id' => null,
                'image_url' => $imageUrl,
                'cta_type' => $ctaType,
                'cta_url' => $ctaUrl,
            ]);

            return [
                'status' => 'not_connected',
                'message' => 'Connect your Google profile first — nothing was sent to Google.',
                'dispatched_to_zernio' => false,
                'zernio_dispatch_id' => null,
                'post_id' => $post->id,
            ];
        }

        $post = GbpPost::create([
            'business_id' => $businessId,
            'connection_id' => $conn->id,
            'post_type' => $postType,
            'content' => $content,
            'status' => 'publishing',
            'zernio_dispatch_id' => null,
            'image_url' => $imageUrl,
            'cta_type' => $ctaType,
            'cta_url' => $ctaUrl,
        ]);

        try {
            $receipt = ($this->client ?? app(ZernioGbpClient::class))->publishPost(
                $connRow->account_ref,
                $content,
                'gbp-post-'.$post->id,
                ['gbp_post_id' => $post->id],
                $imageUrl,
                $ctaType !== null ? ['type' => $ctaType, 'url' => $ctaUrl] : null
            );

            $status = 'failed';
            if ($receipt->status === 'published') {
                $status = 'posted';
            } elseif ($receipt->status === 'scheduled' || $receipt->status === 'publishing') {
                $status = 'publishing';
            }

            $post->update([
                'status' => $status,
                'zernio_dispatch_id' => $receipt->providerPostId,
                'failure_reason' => $status === 'failed' ? $receipt->errorMessage : null,
            ]);

            if ($status === 'posted' && $receipt->providerPostId !== null) {
                Event::dispatch(new GbpPosted($businessId, $post->id, $receipt->providerPostId));
            }

            return [
                'status' => $status,
                'post_id' => $post->id,
                'zernio_dispatch_id' => $receipt->providerPostId,
                'dispatched_to_zernio' => $receipt->providerPostId !== null && $receipt->providerPostId !== '',
                'message' => $status === 'failed' ? ($receipt->errorMessage ?? 'Failed') : 'Success',
            ];
        } catch (GbpRequestFailed $e) {
            $post->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'post_id' => $post->id,
                'zernio_dispatch_id' => null,
                'dispatched_to_zernio' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
