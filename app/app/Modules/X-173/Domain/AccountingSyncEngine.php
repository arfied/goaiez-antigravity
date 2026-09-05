<?php

declare(strict_types=1);

namespace App\Modules\X173\Domain;

use App\Modules\X173\Models\AccountingConnection;
use App\Modules\X173\Models\AccountingSyncConflict;
use App\Modules\X173\Models\AccountMapping;
use App\Modules\X173\Models\SyncRun;

final class AccountingSyncEngine
{
    private const CONFIDENCE_THRESHOLD = 0.85;

    /**
     * Resolves accounting category from transaction description.
     * Category below confidence threshold posts to 'uncategorised' with review flag, NEVER to a guessed code (TEST ANCHOR & G1-03).
     */
    public function inferCategory(string $description, float $inferredConfidence, string $suggestedCategory): array
    {
        if ($inferredConfidence < self::CONFIDENCE_THRESHOLD) {
            return [
                'assigned_category' => 'uncategorised',
                'confidence_score' => $inferredConfidence,
                'flagged_for_review' => true,
                'is_low_confidence' => true,
            ];
        }

        return [
            'assigned_category' => $suggestedCategory,
            'confidence_score' => $inferredConfidence,
            'flagged_for_review' => false,
            'is_low_confidence' => false,
        ];
    }

    public function resolveConflict(int $businessId, int $conflictId, string $resolutionAccount): array
    {
        $conflict = AccountingSyncConflict::where('business_id', $businessId)->findOrFail($conflictId);
        $ref = $conflict->transaction_ref;

        $resolutionAccount = trim($resolutionAccount);
        if ($resolutionAccount === '' || strtolower($resolutionAccount) === 'uncategorised') {
            return [
                'status' => 'refused',
                'message' => sprintf('Posting %s to uncategorised is not a resolution; name the account this line belongs to. Nothing changed.', $ref),
            ];
        }

        if ($conflict->status === 'resolved') {
            return [
                'status' => 'refused',
                'message' => sprintf('%s was already resolved to %s by a person; a resolution is not overwritten silently.', $conflict->transaction_ref, $conflict->assigned_category),
            ];
        }

        $conflict->update([
            'assigned_category' => $resolutionAccount,
            'status' => 'resolved',
            'flagged_for_review' => false,
        ]);

        return [
            'status' => 'resolved',
            'conflict_id' => $conflict->id,
            'assigned_category' => $conflict->assigned_category,
            'message' => sprintf('%s now posts to %s.', $conflict->transaction_ref, $resolutionAccount),
        ];
    }

    public function mapAccount(int $businessId, int $connectionId, string $internalCategory, string $remoteGlAccountId, string $remoteGlAccountName): array
    {
        $internalCategory = trim($internalCategory);
        $remoteGlAccountId = trim($remoteGlAccountId);
        $remoteGlAccountName = trim($remoteGlAccountName);

        if (empty($internalCategory) || empty($remoteGlAccountId) || empty($remoteGlAccountName)) {
            return [
                'status' => 'refused',
                'message' => 'A mapping needs the category, the ledger account id and its name; nothing was saved.',
            ];
        }

        $connection = AccountingConnection::where('business_id', $businessId)->findOrFail($connectionId);

        if (! $connection->is_active) {
            return [
                'status' => 'refused',
                'message' => 'That ledger connection is not active; reconnect it before mapping. Nothing was saved.',
            ];
        }

        $mapping = AccountMapping::updateOrCreate(
            [
                'business_id' => $businessId,
                'connection_id' => $connectionId,
                'internal_category' => $internalCategory,
            ],
            [
                'remote_gl_account_id' => $remoteGlAccountId,
                'remote_gl_account_name' => $remoteGlAccountName,
            ]
        );

        return [
            'status' => 'mapped',
            'mapping_id' => $mapping->id,
            'message' => sprintf('%s now posts to %s · %s.', $internalCategory, $remoteGlAccountId, $remoteGlAccountName),
        ];
    }

    public function rateOf(int $recordsSynced, int $conflictsCount): ?float
    {
        $seen = $recordsSynced + $conflictsCount;

        return $seen === 0 ? null : $conflictsCount / $seen;
    }

    public function errorRate(int $businessId, int $syncRunId): ?float
    {
        $run = SyncRun::where('business_id', $businessId)->findOrFail($syncRunId);

        return $this->rateOf($run->records_synced, $run->conflicts_count);
    }
}
