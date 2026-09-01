<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

it('forbids SET app.business_id outside Tenancy.php', function () {
    $finder = new Finder();
    $finder->files()
        ->in(app_path())
        ->name('*.php')
        ->notName('Tenancy.php');
        
    $offenders = [];
    foreach ($finder as $file) {
        if (str_contains($file->getContents(), 'SET app.business_id')) {
            $offenders[] = $file->getRelativePathname();
        }
    }
    
    expect($offenders)->toBeEmpty();
});
