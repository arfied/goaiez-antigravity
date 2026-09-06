<?php

declare(strict_types=1);

namespace Tests\Modules\X172\Screens;

use App\Models\Business;
use App\Modules\X172\Actions\PortalLinkAction;

class Fixtures
{
    public static function token(Business $biz): string
    {
        return app(PortalLinkAction::class)->handle(
            $biz->id,
            'work_orders',
            1
        )->token;
    }
}
