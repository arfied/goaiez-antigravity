<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

it('can initialize s3 disk without throwing', function () {
    $disk = Storage::disk('s3');
    $this->assertNotNull($disk);
});
