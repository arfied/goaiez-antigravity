<?php
declare(strict_types=1);

namespace App\Modules\X209\Domain;

final class X209Engine
{
    public function validateTarget(string $actorType, string $targetType): array {
        if ($actorType === 'employee' && $targetType !== 'own_day') {
            return ['status' => 'refused', 'reason' => 'employee to own day only'];
        }
        return ['status' => 'ok'];
    }

    public function canReachCustomer(): array {
        return ['status' => 'refused', 'reason' => 'output never reaches a customer'];
    }

    public function getAutonomyLevel(bool $isNewHire): string {
        if ($isNewHire) return 'L1';
        return 'L2';
    }

    public function logAction(string $tenantLog): array {
        return ['status' => 'logged', 'destination' => 'tenant_log'];
    }

    public function canSeeFields(int $employeeId, int $targetId): array {
        if ($employeeId !== $targetId) {
            return ['status' => 'refused', 'reason' => 'cannot see another employee fields'];
        }
        return ['status' => 'ok'];
    }

    public function answerQuestion(string $topic): array {
        if ($topic === 'pay') {
            return ['status' => 'refused', 'reason' => 'refuses to answer about pay'];
        }
        return ['status' => 'ok'];
    }

    public function canAccess(int $employeeId, string $resourceOwnerId): array {
        if ((string)$employeeId !== (string)$resourceOwnerId) {
            return ['status' => 'refused', 'reason' => 'reaches only what that employee may reach'];
        }
        return ['status' => 'ok'];
    }
}
