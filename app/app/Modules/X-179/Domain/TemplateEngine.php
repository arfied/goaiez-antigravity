<?php

declare(strict_types=1);

namespace App\Modules\X179\Domain;

final class TemplateEngine
{
    public function enforceHeaderNaming(string $header): bool
    {
        return ! empty($header);
    }

    public function extractTechStackToOpener(string $techStack): string
    {
        if (empty($techStack)) {
            return 'Welcome!';
        }

        return 'Welcome! We see you use '.$techStack.'.';
    }

    public function excludeBoilerplate(string $rawPage): string
    {
        $content = preg_replace('/<nav\b[^>]*>.*?<\/nav>/is', '', $rawPage);
        $content = preg_replace('/<footer\b[^>]*>.*?<\/footer>/is', '', $content ?? '');
        $content = preg_replace('/<cookie-banner\b[^>]*>.*?<\/cookie-banner>/is', '', $content ?? '');
        return trim($content ?? '');
    }
}
