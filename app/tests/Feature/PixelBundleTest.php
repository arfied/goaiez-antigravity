<?php

declare(strict_types=1);

use App\Models\PixelBundleVersion;

it('serves no-store for unpublished pixel', function () {
    $response = $this->get('/p.js');
    
    $response->assertStatus(200);
    $response->assertHeader('Cache-Control', 'no-store, private'); // Laravel might append private
    $this->assertEquals('/* unpublished */', $response->getContent());
});

it('serves max-age for published pixel', function () {
    PixelBundleVersion::factory()->create([
        'sha' => 'abc1234',
        'contents' => 'console.log("published");',
        'published_at' => now(),
    ]);

    $response = $this->get('/p.js');
    
    $response->assertStatus(200);
    $response->assertHeader('Cache-Control', 'max-age=300, public, stale-while-revalidate=86400');
    $this->assertEquals('console.log("published");', $response->getContent());
});
