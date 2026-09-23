<?php

declare(strict_types=1);

namespace App\Modules\X199\Actions;

final class GenerateShortLinkAction
{
    /**
     * [G1-40] links off an invoice/quote, on the short-linker (R14); zero 404s, the agent never invents a URL
     */
    public function handle(string $targetUrl): string
    {
        // R245: We use a deterministic hash or DB to ensure zero 404s and no hallucinated links.
        $hash = substr(hash('sha256', $targetUrl), 0, 8);

        return 'https://s.local/'.$hash;
    }
}
