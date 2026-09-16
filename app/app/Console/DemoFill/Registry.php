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
            new Fillers\CAgentFiller,
            new Fillers\CReviewsFiller,
            new Fillers\X82Filler,
            new Fillers\X102Filler,
            new Fillers\X104Filler,
            new Fillers\X108Filler,
            new Fillers\X121Filler,
            new Fillers\X129Filler,
            new Fillers\X137Filler,
            new Fillers\X153Filler,
            new Fillers\X155Filler,
            new Fillers\X157Filler,
            new Fillers\X162Filler,
            new Fillers\X163Filler,
            new Fillers\X164Filler,
            new Fillers\X165Filler,
            new Fillers\X166Filler,
            new Fillers\X167Filler,
            new Fillers\X168Filler,
            new Fillers\X170Filler,
            new Fillers\X175Filler,
            new Fillers\X176Filler,
            new Fillers\X177Filler,
            new Fillers\X181Filler,
            new Fillers\X182Filler,
            new Fillers\X186Filler,
            new Fillers\X188Filler,
            new Fillers\X190Filler,
            new Fillers\X199Filler,
            new Fillers\X203Filler,
            new Fillers\X205Filler,
            new Fillers\X206Filler,
            new Fillers\X207Filler,
            new Fillers\X210Filler,
        ];

        usort($fillers, fn ($a, $b) => strcmp($a->module(), $b->module()));

        return $fillers;
    }
}
