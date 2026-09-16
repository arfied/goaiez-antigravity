<?php

namespace App\Console\DemoFill;

class Registry
{
    /**
     * @return DemoFiller[]
     */
    public static function fillers(): array
    {
        $fillers = [
            new Fillers\X82Filler,
            new Fillers\X102Filler,
            new Fillers\CAgentFiller,
            new Fillers\CReviewsFiller,
            new Fillers\X181Filler,
            new Fillers\X153Filler,
            new Fillers\X137Filler,
            new Fillers\X155Filler,
            new Fillers\X162Filler,
            new Fillers\X163Filler,
            new Fillers\X164Filler,
            new Fillers\X165Filler,
            new Fillers\X166Filler,
            new Fillers\X167Filler,
            new Fillers\X168Filler,
            new Fillers\X170Filler,
            new Fillers\X175Filler,
            new Fillers\X188Filler,
        ];

        usort($fillers, fn ($a, $b) => strcmp($a->module(), $b->module()));

        return $fillers;
    }
}
