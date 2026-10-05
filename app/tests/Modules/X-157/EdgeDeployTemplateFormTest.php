<?php

declare(strict_types=1);

namespace Tests\Modules\X157;

use App\Models\Business;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Domain\SiteTemplates;
use App\Modules\X103\Models\Page;
use App\Modules\X155\Models\FormDefinition;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class EdgeDeployTemplateFormTest extends TestCase
{
    use RefreshesTenantDatabase;

    /**
     * Fixture taken from X157Test::test_the_published_form_posts_to_an_address_that_captures (a published page with a
     * form_capture block and a form definition) and EdgeDeployTemplateMenuTest (a business on a site template).
     *
     * @param  array<string, mixed>  $schema
     * @return array{0: string, 1: int, 2: string, 3: int}
     */
    private function publish(?string $template, array $schema): array
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Form Tenant 9931', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        if ($template !== null) {
            Business::whereKey($biz->id)->update(['site_tokens' => json_encode([
                'template' => $template,
                'palette' => SiteTemplates::TEMPLATES[$template]['palette'],
                'type_pairing' => SiteTemplates::TEMPLATES[$template]['type_pairing'],
            ], JSON_THROW_ON_ERROR)]);
        }
        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $site = app(SitePublishAction::class)->handle($biz->id, $page->id, [
            ['type' => 'hero', 'headline' => 'Warm rooms 9932'],
            ['type' => 'form_capture'],
            ['type' => 'contact', 'phone' => '(253) 555-0199'],
        ]);
        $zone = (new EdgeProvisionAction)->handle($biz->id, 'form-9933.com', true);
        $form = FormDefinition::create(['business_id' => $biz->id, 'form_name' => 'Contact', 'slug' => 'contact', 'steps' => [['required' => ['first_name']]], 'schema' => $schema]);

        $deploy = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );
        $this->assertSame('deployed', $deploy['status']);

        return [(string) Storage::disk('local')->get("sites/{$deploy['deploy_hash']}.html"), (int) $biz->id, (string) $deploy['deploy_hash'], (int) $form->id];
    }

    public function test_a_template_site_draws_the_businesss_form_in_its_own_form_section(): void
    {
        [$html, $bizId, $hash, $formId] = $this->publish('trades-pro', ['fields' => [
            ['name' => 'first_name', 'label' => 'Your name 9934', 'type' => 'text'],
            ['name' => 'message', 'label' => 'How can we help 9935', 'type' => 'textarea'],
        ]]);

        $this->assertSame(1, substr_count($html, '<form '));
        $this->assertStringContainsString('<form class="tp-form" method="post" action="/sites/'.$bizId.'/'.$hash.'/forms/'.$formId.'"', $html);
        $this->assertStringContainsString('Your name 9934', $html);
        $this->assertStringContainsString('How can we help 9935', $html);
        // The marker stays, empty, and the form is drawn inside the page before it — not appended after the page.
        $this->assertStringContainsString('<div class="form-capture-x155"></div>', $html);
        $this->assertLessThan(strpos($html, 'class="form-capture-x155"'), strpos($html, '<form '));
    }

    public function test_a_site_without_a_template_keeps_the_shared_form_after_the_page(): void
    {
        [$html, $bizId, $hash, $formId] = $this->publish(null, ['fields' => [
            ['name' => 'first_name', 'label' => 'Your name 9936', 'type' => 'text'],
        ]]);

        $this->assertSame(1, substr_count($html, '<form '));
        $this->assertStringContainsString('<div class="form-capture-x155">'."\n".'<div class="site-block site-block-form', $html);
        $this->assertStringContainsString('action="/sites/'.$bizId.'/'.$hash.'/forms/'.$formId.'"', $html);
        $this->assertStringContainsString('Your name 9936', $html);
    }
}
