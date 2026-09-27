<?php

use App\Contracts\FetchGateway;
use App\Enums\FetchOutcome;
use App\Enums\FetchRefusalReason;
use App\Services\Fetch\PublicAddressGuard;
use Illuminate\Support\Facades\Http;

class FakeDnsGuard extends PublicAddressGuard
{
    public array $records = [];

    protected function resolve(string $host): array
    {
        return $this->records[$host] ?? [];
    }
}

it('identifies public and private IPs', function () {
    expect(PublicAddressGuard::isPublicIp('8.8.8.8'))->toBeTrue()
        ->and(PublicAddressGuard::isPublicIp('127.0.0.1'))->toBeFalse()
        ->and(PublicAddressGuard::isPublicIp('10.1.2.3'))->toBeFalse()
        ->and(PublicAddressGuard::isPublicIp('172.16.0.1'))->toBeFalse()
        ->and(PublicAddressGuard::isPublicIp('192.168.1.1'))->toBeFalse()
        ->and(PublicAddressGuard::isPublicIp('169.254.169.254'))->toBeFalse()
        ->and(PublicAddressGuard::isPublicIp('100.64.0.1'))->toBeFalse()
        ->and(PublicAddressGuard::isPublicIp('0.0.0.0'))->toBeFalse()
        ->and(PublicAddressGuard::isPublicIp('::1'))->toBeFalse()
        ->and(PublicAddressGuard::isPublicIp('fe80::1'))->toBeFalse()
        ->and(PublicAddressGuard::isPublicIp('fc00::1'))->toBeFalse();
});

it('checks URLs and literal IPs', function () {
    $guard = new PublicAddressGuard;

    expect($guard->check('ftp://8.8.8.8/'))->toBeNull()
        ->and($guard->check('http://127.0.0.1/x'))->toBeNull()
        ->and($guard->check('http://[::1]/'))->toBeNull()
        ->and($guard->check('https://8.8.8.8/'))->toBe(['8.8.8.8']);
});

it('resolves and checks hostnames', function () {
    $guard = new FakeDnsGuard;
    $guard->records['private.example'] = ['10.0.0.5'];
    $guard->records['empty.example'] = [];
    $guard->records['mixed.example'] = ['8.8.8.8', '10.0.0.5'];

    expect($guard->check('http://private.example/'))->toBeNull()
        ->and($guard->check('http://empty.example/'))->toBe([])
        ->and($guard->check('http://mixed.example/'))->toBeNull();
});

it('refuses private literal fetches in the gateway without sending', function () {
    Http::fake();

    $result = app(FetchGateway::class)->fetch('tenant_site', 'http://169.254.169.254/latest/meta-data');

    expect($result->outcome)->toBe(FetchOutcome::Refused)
        ->and($result->refusalReason)->toBe(FetchRefusalReason::PrivateAddress);

    Http::assertNothingSent();
});

it('refuses redirects to private addresses', function () {
    $guard = new FakeDnsGuard;
    $guard->records['public.example'] = ['8.8.8.8'];
    app()->instance(PublicAddressGuard::class, $guard);

    Http::fake([
        'public.example/start' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
        'public.example/robots.txt' => Http::response("User-agent: *\nAllow: /", 200),
    ]);

    $result = app(FetchGateway::class)->fetch('tenant_site', 'http://public.example/start');

    expect($result->outcome)->toBe(FetchOutcome::Refused)
        ->and($result->refusalReason)->toBe(FetchRefusalReason::PrivateAddress);
});
