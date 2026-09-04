<?php
declare(strict_types=1);

namespace App\Modules\X179\Domain;

final class TemplateEngine
{
    public function enforceHeaderNaming(string $header): bool
    {
        return !empty($header);
    }

    public function extractTechStackToOpener(string $techStack): string
    {
        if (empty($techStack)) {
            return "Welcome!";
        }
        return "Welcome! We see you use " . $techStack . ".";
    }
}
