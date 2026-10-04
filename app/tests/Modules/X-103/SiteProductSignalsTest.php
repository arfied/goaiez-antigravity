<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Modules\X103\Domain\SiteProductSignals;
use Tests\TestCase;

class SiteProductSignalsTest extends TestCase
{
    private function ld(string $json): string
    {
        return '<script type="application/ld+json">'.$json.'</script>';
    }

    public function test_a_stores_own_products_are_read_with_their_price_picture_and_page(): void
    {
        $html = $this->ld('{"@context":"https://schema.org","@type":"Product","name":"Folk slip mug 7501","url":"/products/folk-mug","description":"<p>Hand-painted &amp; glazed.</p>","image":["//cdn.shopify.com/mug-7501.jpg"],"offers":[{"@type":"Offer","price":"38.00","priceCurrency":"USD"}]}')
            .$this->ld('{"@graph":[{"@type":"WebPage","name":"Shop"},{"@type":["Product"],"name":"Blue vase 7502","image":{"url":"https://shop.example/vase-7502.jpg"},"offers":{"@type":"AggregateOffer","lowPrice":"88.5","priceCurrency":"GBP"}}]}');

        $this->assertSame([
            ['name' => 'Folk slip mug 7501', 'url' => 'https://shop.example/products/folk-mug', 'price_text' => '$38', 'description' => 'Hand-painted & glazed.', 'image_url' => 'https://cdn.shopify.com/mug-7501.jpg'],
            ['name' => 'Blue vase 7502', 'url' => 'https://shop.example/collections/all', 'price_text' => 'from £88.50', 'image_url' => 'https://shop.example/vase-7502.jpg'],
        ], SiteProductSignals::read($html, 'https://shop.example/collections/all'));
    }

    public function test_unsafe_addresses_broken_data_other_types_and_repeats_are_left_out(): void
    {
        $html = $this->ld('{"@type":"ItemList","itemListElement":[{"@type":"ListItem","item":{"@type":"Product","name":"Gift card 7503","url":"javascript:alert(1)","image":"data:image/png;base64,xx"}},{"@type":"ListItem","item":{"@type":"Product","name":"gift CARD 7503"}}]}')
            .$this->ld('{not json')
            .$this->ld('{"@type":"LocalBusiness","name":"Kiln and Clay 7504"}')
            .$this->ld('{"@type":"Product","name":"   "}');

        $this->assertSame([['name' => 'Gift card 7503', 'url' => 'https://shop.example/']], SiteProductSignals::read($html, 'https://shop.example/'));
        $this->assertSame([], SiteProductSignals::read('<html><body>No data here</body></html>', 'https://shop.example/'));
    }
}
