<?php

declare(strict_types=1);

namespace App\Modules\X172\Domain;

final class PortalEngine
{
    public function validateSignaturePad(string $location): bool
    {
        return $location === 'customer_portal';
    }
}
