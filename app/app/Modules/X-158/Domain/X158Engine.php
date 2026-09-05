<?php

declare(strict_types=1);

namespace App\Modules\X158\Domain;

final class X158Engine
{
    public function getConstraints(): array
    {
        return [
            'G3-22' => true,
            'G3-25' => true,
            'G3-46' => true,
            'G5-02' => true,
            'G5-52' => true,
            'G8-37' => true,
            'G9-30' => true,
            'G11-27' => true,
            'G12-06' => true,
            'G12-22' => true,
            'G12-23' => true,
            'G12-24' => true,
            'G12-34' => true,
            'G12-37' => true,
            'G16-02' => true,
            'G16-06' => true,
            'G16-14' => true,
            'G16-22' => true,
            'G16-23' => true,
            'G16-30' => true,
            'G18-26' => true,
            'G16-32' => true,
        ];
    }
}
