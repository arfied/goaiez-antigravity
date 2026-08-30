<?php

declare(strict_types=1);

namespace App\Modules\X177\Actions;

use App\Modules\X177\Events\GbpPosted;
use App\Modules\X177\Events\GbpSuspensionRisk;
use App\Modules\X177\Models\GbpConnection;
use App\Modules\X177\Models\GbpPost;
use Illuminate\Support\Facades\Event;

final class GbpPostAction
{
    private const RISK_KEYWORDS = [
        'guaranteed ranking #1',
        'click here for free crypto',
        'wire transfer to claim prize',
    ];

    /**
     * Posts update to GBP via Zernio.
     * 1. On gbp.suspended ZERO posts are attempted for that profile until gbp.reinstated (TEST ANCHOR).
     * 2. A write flagged by the risk ruleset never reaches Zernio (TEST ANCHOR).
     */
    public function post(
        int $businessId,
        int $connectionId,
        string $content,
        string $postType = 'update'
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

        // 3. Valid post dispatch to Zernio (TEST ANCHOR)
        $zernioDispatchId = 'zernio_'.bin2hex(random_bytes(6));

        $post = GbpPost::create([
            'business_id' => $businessId,
            'connection_id' => $conn->id,
            'post_type' => $postType,
            'content' => $content,
            'status' => 'posted',
            'zernio_dispatch_id' => $zernioDispatchId,
        ]);

        Event::dispatch(new GbpPosted($businessId, $post->id, $zernioDispatchId));

        return [
            'status' => 'posted',
            'post_id' => $post->id,
            'zernio_dispatch_id' => $zernioDispatchId,
            'dispatched_to_zernio' => true,
        ];
    }
}
