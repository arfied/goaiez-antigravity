<?php
declare(strict_types=1);
namespace App\Modules\X189\Domain;

final class X189Engine
{
    public function validateLicense(?string $licenseSource): void
    {
        if (empty($licenseSource) || strtolower($licenseSource) === 'scraped') {
            throw new \DomainException('REFUSES: every image must carry a license_source; scraped images are REFUSED [G16-17]');
        }
    }

    public function validateSurface(string $surface, string $licenseSource): void
    {
        if ($surface === 'job' && strtolower($licenseSource) === 'stock') {
            throw new \DomainException('REFUSES: stock images never render on a job surface [P-131, G16-17]');
        }
    }
}
