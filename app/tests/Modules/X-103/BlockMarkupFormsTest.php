<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Services\Industry\IndustryStartingPoints;
use Tests\TestCase;

class BlockMarkupFormsTest extends TestCase
{
    private const CONTEXT = ['businessName' => '', 'deployHash' => 'weight', 'tenant_storage_url_prefix' => '/m/', 'form_action_base' => '/f'];

    public function test_booking_button_full()
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);

        $block = [
            'type' => 'booking_button',
            'url' => 'https://example.com/book-now',
            'label' => 'Book Now!',
        ];

        $html = app(SiteBlockRenderer::class)->render([$block], ['tokens' => $tokens] + self::CONTEXT);

        $this->assertStringContainsString('https://example.com/book-now', $html);
        $this->assertStringContainsString('Book Now!', $html);
        $this->assertStringContainsString('class="site-block booking "', $html);
        $this->assertStringContainsString('class="site-block__inner"', $html);
        $this->assertStringContainsString('class="actions"', $html);
        $this->assertStringContainsString('class="site-cta site-cta--primary"', $html);
    }

    public function test_booking_button_min()
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);

        $block = [
            'type' => 'booking_button',
            'url' => 'https://example.com',
            'label' => 'Book',
        ];

        $html = app(SiteBlockRenderer::class)->render([$block], ['tokens' => $tokens] + self::CONTEXT);

        $this->assertStringContainsString('class="site-block booking "', $html);
    }

    public function test_booking_form_full()
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);

        $block = [
            'type' => 'booking_form',
            'heading' => 'Request an Appointment',
            'service' => 'Haircut',
            'label' => 'Send Request',
        ];

        $html = app(SiteBlockRenderer::class)->render([$block], ['tokens' => $tokens] + self::CONTEXT);

        $this->assertStringContainsString('Request an Appointment', $html);
        $this->assertStringContainsString('Haircut', $html);
        $this->assertStringContainsString('Send Request', $html);
        $this->assertStringContainsString('class="site-block site-block-booking "', $html);
        $this->assertStringContainsString('class="site-block__inner"', $html);
        $this->assertStringContainsString('class="card"', $html);
        $this->assertStringContainsString('class="site-cta site-cta--primary"', $html);
    }

    public function test_booking_form_min()
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);

        $block = [
            'type' => 'booking_form',
            'heading' => 'Book',
        ];

        $html = app(SiteBlockRenderer::class)->render([$block], ['tokens' => $tokens] + self::CONTEXT);

        $this->assertStringContainsString('class="site-block site-block-booking "', $html);
    }

    public function test_contact_full()
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);

        $block = [
            'type' => 'contact',
            'address' => '123 Fake St',
            'phone' => '555-1234',
            'email' => 'hello@example.com',
            'hours' => [
                ['day' => 'Monday', 'open' => '9am', 'close' => '5pm'],
                ['day' => 'Tuesday', 'open' => 'Closed', 'close' => ''],
            ],
            'facts' => ['wifi' => 'Yes'],
            'industry_facts' => [['label' => 'License', 'value' => 'ABC-123']],
        ];

        $html = app(SiteBlockRenderer::class)->render([$block], ['tokens' => $tokens] + self::CONTEXT);

        $this->assertStringContainsString('Visit', $html);
        $this->assertStringContainsString('Call', $html);
        $this->assertStringContainsString('Write', $html);
        $this->assertStringContainsString('Hours', $html);
        $this->assertStringContainsString('123 Fake St', $html);
        $this->assertStringContainsString('555-1234', $html);
        $this->assertStringContainsString('hello@example.com', $html);
        $this->assertStringContainsString('Monday: 9am - 5pm', $html);
        $this->assertStringContainsString('Tuesday: Closed', $html);
        $this->assertStringContainsString('wifi: Yes', $html);
        $this->assertStringContainsString('License: ABC-123', $html);

        $this->assertStringContainsString('<a href="tel:5551234">', $html);
        $this->assertStringContainsString('<a href="mailto:hello@example.com">', $html);

        $this->assertStringNotContainsString('Address:', $html);
        $this->assertStringNotContainsString('Phone:', $html);
        $this->assertStringNotContainsString('Email:', $html);

        $this->assertStringContainsString('class="site-block__inner"', $html);
    }

    public function test_contact_min()
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);

        $block = [
            'type' => 'contact',
        ];

        $html = app(SiteBlockRenderer::class)->render([$block], ['tokens' => $tokens] + self::CONTEXT);

        $this->assertStringContainsString('class="site-block contact', $html);
    }

    public function test_faq_full()
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);

        $block = [
            'type' => 'faq',
            'items' => [
                ['question' => 'Q1', 'answer' => 'A1'],
                ['question' => 'Q2', 'answer' => 'A2'],
            ],
        ];

        $html = app(SiteBlockRenderer::class)->render([$block], ['tokens' => $tokens] + self::CONTEXT);

        $this->assertStringContainsString('Q1', $html);
        $this->assertStringContainsString('A1', $html);
        $this->assertStringContainsString('Q2', $html);
        $this->assertStringContainsString('A2', $html);
        $this->assertStringContainsString('id="faq-x176"', $html);
        $this->assertStringContainsString('class="site-block__inner"', $html);
    }

    public function test_faq_min()
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);

        $block = [
            'type' => 'faq',
            'question' => 'Q',
            'answer' => 'A',
        ];

        $html = app(SiteBlockRenderer::class)->render([$block], ['tokens' => $tokens] + self::CONTEXT);

        $this->assertStringContainsString('id="faq-x176"', $html);
    }

    public function test_video_embed_full()
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);

        $block = [
            'type' => 'video_embed',
            'name' => 'My Video',
            'contentUrl' => 'https://youtube.com/watch?v=123',
            'uploadDate' => '2024-01-01',
        ];

        $html = app(SiteBlockRenderer::class)->render([$block], ['tokens' => $tokens] + self::CONTEXT);

        $this->assertStringContainsString('My Video', $html);
        $this->assertStringContainsString('https://youtube.com/watch?v=123', $html);
        $this->assertStringContainsString('id="videos-x176"', $html);
        $this->assertStringContainsString('class="site-block__inner"', $html);
        $this->assertStringContainsString('class="media media--wide"', $html);
    }

    public function test_video_embed_min()
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);

        $block = [
            'type' => 'video_embed',
            'name' => 'V',
            'contentUrl' => 'U',
            'uploadDate' => 'D',
        ];

        $html = app(SiteBlockRenderer::class)->render([$block], ['tokens' => $tokens] + self::CONTEXT);

        $this->assertStringContainsString('id="videos-x176"', $html);
    }

    public function test_form_full()
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);

        $block = [
            'type' => 'form',
            'definition_id' => 'def1',
            'fields' => [
                ['name' => 'f1', 'label' => 'Field 1', 'type' => 'text'],
            ],
            'honeypot' => 'hp',
            'required' => ['f1'],
        ];

        $html = app(SiteBlockRenderer::class)->render([$block], ['tokens' => $tokens] + self::CONTEXT);

        $this->assertStringContainsString('def1', $html);
        $this->assertStringContainsString('f1', $html);
        $this->assertStringContainsString('Field 1', $html);
        $this->assertStringContainsString('hp', $html);
        $this->assertStringContainsString('required', $html);

        $this->assertStringContainsString('class="site-block site-block-form ', $html);
        $this->assertStringContainsString('class="site-block__inner"', $html);
        $this->assertStringContainsString('class="card"', $html);
        $this->assertStringContainsString('class="site-cta site-cta--primary"', $html);
    }

    public function test_form_min()
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);

        $block = [
            'type' => 'form',
            'definition_id' => 'def2',
            'fields' => [],
            'honeypot' => 'hp2',
        ];

        $html = app(SiteBlockRenderer::class)->render([$block], ['tokens' => $tokens] + self::CONTEXT);

        $this->assertStringContainsString('class="site-block site-block-form ', $html);
    }
}
