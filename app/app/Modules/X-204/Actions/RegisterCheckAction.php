<?php

declare(strict_types=1);

namespace App\Modules\X204\Actions;

use App\Modules\X204\Models\ComplianceRegister;

final class RegisterCheckAction
{
    public function handle(int $businessId, string $registerName): array
    {
        $reg = ComplianceRegister::where('business_id', $businessId)
            ->where('register_name', $registerName)
            ->first();

        return [
            'register_name' => $registerName,
            'is_compliant' => $reg !== null && $reg->status === 'compliant',
            'status' => $reg ? $reg->status : 'unregistered',
            'slot_states' => $reg ? $reg->slot_states : [],
        ];
    }
}
