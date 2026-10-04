<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Modules\X103\Actions\SiteStockPhotoAction;
use App\Modules\X103\Domain\SiteTemplates;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class SiteStockPhotoTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');
    }

    private static function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    /** @param list<array<string, mixed>> $hits */
    private function pixabay(array $hits): void
    {
        Http::fake([
            'pixabay.com/api/*' => Http::response(['total' => count($hits), 'totalHits' => count($hits), 'hits' => $hits], 200, ['Content-Type' => 'application/json']),
            'pixabay.com/get/*' => Http::response(self::jpeg(1280, 853), 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    private static function hit(int $id, string $tags, int $width, int $height, string $url): array
    {
        return ['id' => $id, 'pageURL' => 'https://pixabay.com/photos/'.$id.'/', 'tags' => $tags, 'largeImageURL' => $url, 'imageWidth' => $width, 'imageHeight' => $height];
    }

    public function test_every_template_has_a_stock_search(): void
    {
        $searches = array_keys(SiteStockPhotoAction::QUERIES);
        $templates = array_keys(SiteTemplates::TEMPLATES);
        sort($searches);
        sort($templates);
        $this->assertSame($templates, $searches);
    }

    public function test_without_the_key_nothing_is_searched(): void
    {
        Http::fake();

        $res = app(SiteStockPhotoAction::class)->handle(7, 'calm-spa');

        $this->assertSame(['status' => 'skipped', 'reason' => 'credential_not_configured'], $res);
        $this->assertFalse(app(SiteStockPhotoAction::class)->available());
        Http::assertNothingSent();
    }

    public function test_a_wide_photo_without_people_is_downloaded_stored_and_named_as_stock(): void
    {
        config(['credentials.pixabay_api_key' => 'fake-pixabay']);
        $this->pixabay([
            self::hit(8101, 'spa, towel, tall', 800, 1200, 'https://pixabay.com/get/tall-8101.jpg'),
            self::hit(8102, 'woman, massage, spa', 1920, 1280, 'https://pixabay.com/get/person-8102.jpg'),
            self::hit(8103, 'spa, stones, candles, wellness', 1920, 1280, 'https://pixabay.com/get/scene-8103.jpg'),
        ]);

        $res = app(SiteStockPhotoAction::class)->handle(7, 'calm-spa');

        $this->assertSame('stored', $res['status']);
        $this->assertSame('site-inventory/7/stock-pixabay-8103.jpg', $res['path']);
        $this->assertSame('Spa, stones, candles (stock photo)', $res['alt']);
        $this->assertSame([1280, 853], [$res['width'], $res['height']]);
        Storage::disk('local')->assertExists('site-inventory/7/stock-pixabay-8103.jpg');
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://pixabay.com/api/') && $r['key'] === 'fake-pixabay'
            && $r['q'] === SiteStockPhotoAction::QUERIES['calm-spa'] && $r['orientation'] === 'horizontal' && $r['safesearch'] === 'true');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'tall-8101') || str_contains($r->url(), 'person-8102'));
    }

    public function test_a_search_is_kept_for_a_day_so_the_next_business_of_that_trade_asks_pixabay_once(): void
    {
        config(['credentials.pixabay_api_key' => 'fake-pixabay']);
        $this->pixabay([self::hit(8111, 'nail polish, bottles', 1920, 1280, 'https://pixabay.com/get/polish-8111.jpg')]);

        app(SiteStockPhotoAction::class)->handle(7, 'polish-bar');
        app(SiteStockPhotoAction::class)->handle(8, 'polish-bar');

        $searches = collect(Http::recorded())->filter(fn ($pair) => str_starts_with($pair[0]->url(), 'https://pixabay.com/api/'))->count();
        $this->assertSame(1, $searches);
    }

    public function test_a_picture_on_any_other_host_is_never_downloaded(): void
    {
        config(['credentials.pixabay_api_key' => 'fake-pixabay']);
        $this->pixabay([self::hit(8121, 'tools, hammer', 1920, 1280, 'https://elsewhere.example.com/tools-8121.jpg')]);

        $res = app(SiteStockPhotoAction::class)->handle(7, 'trades-pro');

        $this->assertSame(['status' => 'none', 'reason' => 'download_failed'], $res);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'elsewhere.example.com'));
    }
}
