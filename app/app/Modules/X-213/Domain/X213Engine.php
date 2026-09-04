<?php
declare(strict_types=1);
namespace App\Modules\X213\Domain;

final class X213Engine
{
    public function validateVisionCheck(?string $screenshotArtifactUrl): void
    {
        if (empty($screenshotArtifactUrl)) {
            throw new \DomainException('REFUSES: a pass with no artifact did not happen');
        }
    }
}