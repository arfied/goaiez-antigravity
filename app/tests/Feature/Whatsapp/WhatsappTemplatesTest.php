<?php

declare(strict_types=1);

use App\Models\Business;
use App\Modules\CWhatsapp\Actions\TemplateSubmitAction;
use App\Modules\CWhatsapp\Events\TemplateApproved;
use App\Modules\CWhatsapp\Models\WhatsappConnection;
use App\Modules\CWhatsapp\Models\WhatsappTemplate;
use App\Modules\CWhatsapp\Ui\TemplateStatusCard;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function () {
    Http::preventStrayRequests();
    app(DefaultsRegistry::class)->set('whatsapp.zernio_enabled', true, 'test');
    config([
        'credentials.zernio_api_key' => 'test-key',
        'credentials.zernio_webhook_secret' => 'test_secret',
    ]);
});

function postZernioWebhookTemplates(TestCase $test, array $payloadData): TestResponse
{
    $payload = json_encode($payloadData, JSON_THROW_ON_ERROR);
    $sig = hash_hmac('sha256', $payload, 'test_secret');

    return $test->call(
        'POST',
        '/webhooks/zernio',
        [], [], [],
        ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );
}

function setupConnectedTenantTemplates(): Business
{
    $biz = TestCase::provisionTenant();
    Tenancy::set($biz->id);

    DB::table('gbp_profile_bindings')->insert([
        'business_id' => $biz->id,
        'profile_ref' => 'profile_5900',
    ]);

    $connection = new WhatsappConnection;
    $connection->business_id = $biz->id;
    $connection->status = 'connected';
    $connection->account_ref = 'acct_wa_5901';
    $connection->save();

    return $biz;
}

it('(a) submits template through Zernio', function () {
    $biz = setupConnectedTenantTemplates();
    Tenancy::set($biz->id);

    Http::fake([
        'zernio.com/api/v1/whatsapp/templates' => Http::response(['success' => true, 'template' => ['id' => 'tpl_5902', 'status' => 'PENDING']], 200),
    ]);

    $action = app(TemplateSubmitAction::class);
    $template = $action->handle($biz->id, 'order_update_5903', 'utility', 'Hello {{1}}');

    expect($template->status)->toBe('pending_approval');
    expect($template->provider_template_ref)->toBe('tpl_5902');

    Http::assertSent(function (Request $request) {
        if ($request->url() !== 'https://zernio.com/api/v1/whatsapp/templates') {
            return false;
        }

        $data = $request->data();

        return $data['accountId'] === 'acct_wa_5901'
            && $data['category'] === 'UTILITY'
            && $data['components'][0]['type'] === 'body';
    });
});

it('(b) handles 400 from Zernio', function () {
    $biz = setupConnectedTenantTemplates();
    Tenancy::set($biz->id);

    Http::fake([
        '*' => Http::response(['code' => 'invalid_format'], 400),
    ]);

    $action = app(TemplateSubmitAction::class);
    $template = $action->handle($biz->id, 'order_update_5903', 'utility', 'Hello {{1}}');

    expect($template->status)->toBe('submit_failed');
    expect($template->status_reason)->toBe('invalid_format');
});

it('(c) saves as draft with no connection', function () {
    $biz = TestCase::provisionTenant();
    Tenancy::set($biz->id);

    $action = app(TemplateSubmitAction::class);
    $template = $action->handle($biz->id, 'order_update_5903', 'utility', 'Hello {{1}}');

    expect($template->status)->toBe('draft');
    Http::assertNothingSent();
});

it('(d) refuses Bad Name', function () {
    $biz = setupConnectedTenantTemplates();
    Tenancy::set($biz->id);

    $action = app(TemplateSubmitAction::class);
    $action->handle($biz->id, 'Bad Name', 'utility', 'Hello {{1}}');
})->throws(InvalidArgumentException::class, 'Template names use lowercase letters, numbers and underscores, starting with a letter.');

it('(e) webhook updates to approved', function () {
    $biz = setupConnectedTenantTemplates();
    Tenancy::set($biz->id);

    DB::table('whatsapp_templates')->insert([
        'business_id' => $biz->id,
        'name' => 'order_update_5903',
        'category' => 'utility',
        'language' => 'en_US',
        'body_text' => 'Hello',
        'status' => 'pending_approval',
        'provider_template_ref' => 'tpl_5902',
    ]);

    Event::fake([TemplateApproved::class]);

    $payload = [
        'id' => 'evt_5904',
        'event' => 'whatsapp.template.status_updated',
        'account' => [
            'accountId' => 'acct_wa_5901',
            'profileId' => 'profile_5900',
        ],
        'template' => [
            'templateId' => 'tpl_5902',
            'name' => 'order_update_5903',
            'language' => 'en_US',
            'status' => 'APPROVED',
            'reason' => 'NONE',
        ],
    ];

    $response = postZernioWebhookTemplates($this, $payload);
    $response->assertStatus(200);

    Tenancy::set($biz->id);
    $template = WhatsappTemplate::where('business_id', $biz->id)->first();
    expect($template->status)->toBe('approved');
    expect($template->status_reason)->toBeNull();

    Event::assertDispatched(TemplateApproved::class, 1);
});

it('(f) webhook updates to rejected', function () {
    $biz = setupConnectedTenantTemplates();
    Tenancy::set($biz->id);

    DB::table('whatsapp_templates')->insert([
        'business_id' => $biz->id,
        'name' => 'order_update_5903',
        'category' => 'utility',
        'language' => 'en_US',
        'body_text' => 'Hello',
        'status' => 'pending_approval',
        'provider_template_ref' => 'tpl_5902',
    ]);

    Event::fake([TemplateApproved::class]);

    $payload = [
        'id' => 'evt_5904',
        'event' => 'whatsapp.template.status_updated',
        'account' => [
            'accountId' => 'acct_wa_5901',
            'profileId' => 'profile_5900',
        ],
        'template' => [
            'templateId' => 'tpl_5902',
            'name' => 'order_update_5903',
            'language' => 'en_US',
            'status' => 'REJECTED',
            'reason' => 'INVALID_FORMAT',
        ],
    ];

    $response = postZernioWebhookTemplates($this, $payload);
    $response->assertStatus(200);

    Tenancy::set($biz->id);
    $template = WhatsappTemplate::where('business_id', $biz->id)->first();
    expect($template->status)->toBe('rejected');
    expect($template->status_reason)->toBe('INVALID_FORMAT');

    Event::assertNotDispatched(TemplateApproved::class);
});

it('(g) refresh sets status from api', function () {
    $biz = setupConnectedTenantTemplates();
    Tenancy::set($biz->id);

    DB::table('whatsapp_templates')->insert([
        'business_id' => $biz->id,
        'name' => 'order_update_5903',
        'category' => 'utility',
        'language' => 'en_US',
        'body_text' => 'Hello',
        'status' => 'pending_approval',
        'provider_template_ref' => 'tpl_5902',
    ]);

    Http::fake([
        'zernio.com/api/v1/whatsapp/templates*' => Http::response(['success' => true, 'templates' => [
            ['id' => 'tpl_5902', 'name' => 'order_update_5903', 'language' => 'en_US', 'status' => 'APPROVED'],
        ]], 200),
    ]);

    Livewire::test(TemplateStatusCard::class, ['businessId' => $biz->id])
        ->call('refresh');

    $template = WhatsappTemplate::where('business_id', $biz->id)->first();
    expect($template->status)->toBe('approved');
});
