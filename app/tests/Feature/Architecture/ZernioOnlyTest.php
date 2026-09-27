<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

it('reaches social, review and messaging networks only through Zernio', function () {
    $finder = new Finder;
    $finder->files()
        ->in(app_path())
        ->name('*.php');

    $permitted = [
        'Services/Oauth/MetaTokenRefresher.php' => 'Facebook sign-in token refresh — owner decision pending',
    ];

    $offenders = [];
    foreach ($finder as $file) {
        $path = $file->getRelativePathname();
        if (array_key_exists($path, $permitted)) {
            continue;
        }

        if (preg_match('/graph\.facebook\.com|graph\.instagram\.com|mybusiness[a-z]*\.googleapis\.com|businessprofileperformance\.googleapis\.com|graph\.whatsapp|MetaService|GoogleBusinessService/', $file->getContents())) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBeEmpty('Offending files: '.implode(', ', $offenders).'. Facebook, Instagram, WhatsApp and Google Business Profile go through Zernio only (app/Services/Zernio, app/Services/Gbp/ZernioGbpClient.php). A direct call is how this repo kept drifting back to the official APIs — see .agents/rules/10-supervisor.md.');
});
