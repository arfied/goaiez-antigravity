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
            'is_compliant' => $reg ? $reg->status === 'compliant' : true,
            'status' => $reg ? $reg->status : 'compliant',
            'slot_states' => $reg ? $reg->slot_states : [],
        ];
    }
}
