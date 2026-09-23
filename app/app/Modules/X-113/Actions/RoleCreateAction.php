<?php

declare(strict_types=1);

namespace App\Modules\X113\Actions;

use App\Modules\X113\Models\Role;
use InvalidArgumentException;

final class RoleCreateAction
{
    public function handle(int $businessId, string $name, ?string $description = null): Role
    {
        if (Role::where('business_id', $businessId)->where('name', $name)->exists()) {
            throw new InvalidArgumentException("A role named '{$name}' already exists in this account.");
        }

        return Role::create([
            'business_id' => $businessId,
            'name' => $name,
            'description' => $description,
        ]);
    }
}
