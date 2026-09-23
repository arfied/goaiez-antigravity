<?php

declare(strict_types=1);

namespace App\Modules\X202\Domain;

use App\Modules\X202\Events\ApprovalDecided;
use App\Modules\X202\Events\ApprovalEscalated;
use App\Modules\X202\Events\ApprovalExpired;
use App\Modules\X202\Events\ApprovalRaised;
use App\Modules\X202\Models\ApprovalChain;
use App\Modules\X202\Models\ApprovalItem;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class ApprovalDeskEngine
{
    public const EXPIRY_HOURS = 72;

    public function __construct(
        private readonly DefaultsRegistry $registry
    ) {}

    private function expiryHours(): int
    {
        return $this->registry->int('approvals.expiry_hours');
    }

    /**
     * Enqueue approval item with bare error rejection (TEST ANCHOR).
     */
    public function enqueue(
        int $businessId,
        ?string $itemType,
        ?string $subject,
        ?array $payload,
        string $autonomyLevel = 'L2',
        bool $isL1Forever = false,
        ?int $expiresInHours = null
    ): array {
        // 1. Bare error check: Enqueue a bare error the write is REFUSED (TEST ANCHOR)
        $expiresInHours ??= $this->expiryHours();
        if (empty($itemType) || empty($subject) || empty($payload) || isset($payload['error_only'])) {
            return [
                'status' => 'refused',
                'refusal_code' => 'INVALID_APPROVAL_PAYLOAD',
                'message' => 'Cannot enqueue bare errors or empty payloads to approval desk',
            ];
        }

        $item = ApprovalItem::create([
            'business_id' => $businessId,
            'item_type' => $itemType,
            'subject' => $subject,
            'payload' => $payload,
            'autonomy_level' => $autonomyLevel,
            'is_l1_forever' => $isL1Forever,
            'status' => 'pending',
            'expires_at' => now()->addHours($expiresInHours),
            'magic_token' => Str::random(32),
        ]);

        Event::dispatch(new ApprovalRaised(
            businessId: $businessId,
            approvalItemId: $item->id,
            itemType: $itemType,
            subject: $subject
        ));

        return [
            'status' => 'enqueued',
            'approval_item_id' => $item->id,
            'item' => $item,
            'magic_url' => "https://app.goaiez.com/approve/{$item->magic_token}",
        ];
    }

    /**
     * Determine if a decision authorizes an automated publish. (R245)
     */
    public function authorizesPublish(bool $isL1Forever, string $status): bool
    {
        return $status === 'approved' && ! $isL1Forever;
    }

    /**
     * Decide approval item (approve or reject).
     */
    public function decide(
        int $businessId,
        int $approvalItemId,
        string $decision, // approved, rejected
        ?int $userId = null,
        ?string $comment = null
    ): array {
        return DB::transaction(function () use ($businessId, $approvalItemId, $decision, $userId, $comment) {
            $item = ApprovalItem::where('business_id', $businessId)->findOrFail($approvalItemId);

            $chain = $item->approval_chain_id !== null
                ? ApprovalChain::where('business_id', $businessId)->find($item->approval_chain_id)
                : null;
            $stepsCount = $chain->steps_count ?? 1;

            $newComment = null;
            if ($comment !== null) {
                $timestamp = now()->toIso8601String();
                $newLine = "[{$timestamp}] {$comment}";
                $newComment = $item->decision_comment
                    ? $item->decision_comment."\n".$newLine
                    : $newLine;
            }

            // A sequential chain advances one desk per approval; only the last step decides,
            // and ApprovalDecided fires only on the step that sets a terminal status (R245).
            if ($decision === 'approved' && $item->current_step < $stepsCount) {
                $updates = ['current_step' => $item->current_step + 1];

                if ($newComment !== null) {
                    $updates['decision_comment'] = $newComment;
                }

                $item->update($updates);

                return [
                    'approval_item_id' => $item->id,
                    'status' => 'pending',
                    'current_step' => $item->current_step,
                    'publish_authorized' => false,
                ];
            }

            $updates = [
                'status' => $decision,
                'decided_by_user_id' => $userId,
                'decided_at' => now(),
            ];

            if ($newComment !== null) {
                $updates['decision_comment'] = $newComment;
            }

            $item->update($updates);

            Event::dispatch(new ApprovalDecided(
                businessId: $businessId,
                approvalItemId: $item->id,
                decision: $decision
            ));

            return [
                'approval_item_id' => $item->id,
                'status' => $decision,
                'current_step' => $item->current_step,
                'decided_at' => $item->decided_at->toIso8601String(),
                'publish_authorized' => $this->authorizesPublish((bool) $item->is_l1_forever, $decision),
            ];
        });
    }

    /**
     * Batch approve items — L1-forever items CANNOT be batch-approved (TEST ANCHOR).
     */
    public function batchApprove(int $businessId, array $itemIds, ?int $userId = null): array
    {
        return DB::transaction(function () use ($businessId, $itemIds, $userId) {
            $items = ApprovalItem::where('business_id', $businessId)
                ->whereIn('id', $itemIds)
                ->where('status', 'pending')
                ->get();

            $approvedIds = [];
            $skippedL1ForeverIds = [];

            foreach ($items as $item) {
                // L1-forever check: cannot be batch-approved (TEST ANCHOR)
                if ($item->is_l1_forever) {
                    $skippedL1ForeverIds[] = $item->id;

                    continue;
                }

                $item->update([
                    'status' => 'approved',
                    'decided_by_user_id' => $userId,
                    'decided_at' => now(),
                ]);

                Event::dispatch(new ApprovalDecided($businessId, $item->id, 'approved'));
                $approvedIds[] = $item->id;
            }

            return [
                'approved_count' => count($approvedIds),
                'approved_ids' => $approvedIds,
                'skipped_l1_forever_ids' => $skippedL1ForeverIds,
            ];
        });
    }

    /**
     * Check expirations: Expired items appear on a human's screen, not in a void (TEST ANCHOR).
     */
    public function processExpirations(int $businessId): array
    {
        $expiredItems = ApprovalItem::where('business_id', $businessId)
            ->where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->get();

        $processedIds = [];
        foreach ($expiredItems as $item) {
            $item->update(['status' => 'expired']);

            Event::dispatch(new ApprovalExpired($businessId, $item->id));
            Event::dispatch(new ApprovalEscalated($businessId, $item->id, '72h SLA expired — routed to human manager queue'));

            $processedIds[] = $item->id;
        }

        return [
            'expired_count' => count($processedIds),
            'expired_item_ids' => $processedIds,
            'routed_to_human_screen' => true,
        ];
    }
}
