<?php
declare(strict_types=1);
namespace App\Modules\X191\Domain;

final class X191Engine
{
    public function validateFollowUp(int $currentFollowUpCount): void
    {
        if ($currentFollowUpCount >= 1) {
            throw new \DomainException('REFUSES: ONE follow-up only [G8-20, G12-07, G17-06]');
        }
    }

    public function validatePitch(bool $hasPageFact): void
    {
        if (!$hasPageFact) {
            throw new \DomainException('REFUSES: a pitch that names nothing TRUE about the page [G11-34]');
        }
    }
}
