<?php

declare(strict_types=1);

namespace App\Modules\X161\Domain;

final class X161Engine
{
    public function getConstraints(): array
    {
        return [
            'G2-69' => true,
            'G6-01' => true,
            'G6-10' => true,
            'G6-25' => true,
            'G9-12' => true,
            'G11-33' => true,
        ];
    }
}
