<?php

declare(strict_types=1);

namespace Tests\Modules\X138;

use App\Modules\X103\Actions\PageCreateAction;
use App\Modules\X138\Actions\PageEarningsAction;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PageEarningsActionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_page_earnings_read_the_pixel_mart(): void
    {
        $biz = $this->provisionTenant();
        Tenancy::set((int) $biz->id);

        $pageAction = app(PageCreateAction::class);
        $page1 = $pageAction->handle($biz->id, 'home', 'Distinctive home 4471');
        $page1->is_published = true;
        $page1->save();

        $page2 = $pageAction->handle($biz->id, 'services', 'Distinctive services 4472');
        $page2->is_published = true;
        $page2->save();

        $action = app(PageEarningsAction::class);
        $res = $action->handle($biz->id);

        $this->assertFalse($res['measured']);
        $this->assertCount(2, $res['pages']);
        $this->assertSame('/', $res['pages'][0]['path']);
        $this->assertSame('/services', $res['pages'][1]['path']);

        measurementPageDay($biz->id, now()->toDateString(), '/services', 37, 2);
        measurementPageDay($biz->id, now()->subDays(40)->toDateString(), '/services', 99);

        $res2 = $action->handle($biz->id);

        $this->assertTrue($res2['measured']);
        $this->assertSame(0, $res2['pages'][0]['pageviews']);
        $this->assertSame(0, $res2['pages'][0]['conversions']);
        $this->assertSame(37, $res2['pages'][1]['pageviews']);
        $this->assertSame(2, $res2['pages'][1]['conversions']);
    }
}
