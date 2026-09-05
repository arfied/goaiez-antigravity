<?php

declare(strict_types=1);

namespace App\Modules\X160\Domain;

final class X160Engine
{
    public function getConstraints(): array
    {
        return [
            'G2-30' => true,
            'G9-25' => true,
        ];
    }
}
