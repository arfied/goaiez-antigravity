<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Modules\X137\Actions\LinkShortAction;
use App\Modules\X137\Models\LinkClick;
use App\Support\Tenancy;
use Tests\TestCase;

class ShortLinkRedirectTest extends TestCase
{
    public function test_known_code_redirects_and_records_click(): void
    {
        $biz = $this->provisionTenant();

        Tenancy::set((int) $biz->id);
        $shortAction = new LinkShortAction;
        $link = $shortAction->handle($biz->id, 'https://example.com/dest', 'flyer_a');
        Tenancy::forget();

        $this->get("/l/{$biz->id}/{$link->short_code}")
            ->assertRedirect('https://example.com/dest');

        Tenancy::set((int) $biz->id);
        $this->assertEquals(1, LinkClick::where('short_link_id', $link->id)->count());
        Tenancy::forget();
    }

    public function test_unknown_code_returns_404_and_no_click_recorded(): void
    {
        $biz = $this->provisionTenant();

        $this->get("/l/{$biz->id}/unknown_code")
            ->assertNotFound();

        Tenancy::set((int) $biz->id);
        $this->assertEquals(0, LinkClick::count());
        Tenancy::forget();
    }

    public function test_known_code_with_empty_destination_returns_404_and_no_click(): void
    {
        $biz = $this->provisionTenant();

        Tenancy::set((int) $biz->id);
        $shortAction = new LinkShortAction;
        // Using spaces to represent empty destination as X137Test does: $link = $this->shortAction->handle($biz->id, '   ', 'flyer_a');
        $link = $shortAction->handle($biz->id, '   ', 'flyer_a');
        Tenancy::forget();

        $this->get("/l/{$biz->id}/{$link->short_code}")
            ->assertNotFound();

        Tenancy::set((int) $biz->id);
        $this->assertEquals(0, LinkClick::where('short_link_id', $link->id)->count());
        Tenancy::forget();
    }
}
