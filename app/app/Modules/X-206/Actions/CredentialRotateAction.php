<?php

declare(strict_types=1);

namespace App\Modules\X206\Actions;

use App\Modules\X206\Models\Credential;

final class CredentialRotateAction
{
    public function __construct(private readonly CredentialStoreAction $storer) {}

    public function handle(int $businessId, string $serviceName, string $newSecret): Credential
    {
        return $this->storer->handle($businessId, $serviceName, $newSecret);
    }
}
