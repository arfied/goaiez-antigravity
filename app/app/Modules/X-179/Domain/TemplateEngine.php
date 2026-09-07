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

    public function detectPlatform(string $rawPage): ?string
    {
        if (stripos($rawPage, 'cdn.shopify.com') !== false || stripos($rawPage, 'Shopify.theme') !== false) {
            return 'Shopify';
        }
        if (stripos($rawPage, 'wp-content/plugins/woocommerce') !== false || stripos($rawPage, 'woocommerce') !== false) {
            return 'WooCommerce';
        }
        if (stripos($rawPage, 'mage/') !== false || stripos($rawPage, 'Magento') !== false) {
            return 'Magento';
        }
        if (stripos($rawPage, 'bigcommerce.com') !== false) {
            return 'BigCommerce';
        }

        return null;
    }

    public function excludeBoilerplate(string $rawPage): string
    {
        $content = preg_replace('/<nav\b[^>]*>.*?<\/nav>/is', '', $rawPage);
        $content = preg_replace('/<footer\b[^>]*>.*?<\/footer>/is', '', $content ?? '');
        $content = preg_replace('/<cookie-banner\b[^>]*>.*?<\/cookie-banner>/is', '', $content ?? '');

        return trim($content ?? '');
    }
}
