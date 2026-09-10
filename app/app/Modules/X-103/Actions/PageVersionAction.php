<?php

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\PageVersion;

class PageVersionAction
{
    public function forCommit(string $commitId): ?PageVersion
    {
        return PageVersion::where('commit_id', $commitId)->first();
    }

    public function recordSslInstalled(string $commitId, bool $sslInstalled): void
    {
        PageVersion::where('commit_id', $commitId)->update(['ssl_installed' => $sslInstalled]);
    }
}
