<?php

declare(strict_types=1);

namespace App\Modules\X159\Domain;

final class X159Engine
{
    public function getConstraints(): array
    {
        return [
            'G9-03' => true,
        ];
    }
}
