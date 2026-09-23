<?php

declare(strict_types=1);

namespace App\Modules\X189\Ui;

use App\Http\Controllers\Controller;
use App\Modules\X189\Models\BrandedMedia;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class X189MediaController extends Controller
{
    public function __invoke(Request $request, int $business, int $branded): StreamedResponse
    {
        unset($request);

        return Tenancy::actingAs($business, function () use ($business, $branded): StreamedResponse {
            $row = BrandedMedia::query()->findOrFail($branded);

            // The path is re-derived from `source_asset_url` and `destination`.
            $path = 'branded/'.$business.'/'.md5($row->source_asset_url).'-'.$row->destination.'.jpg';

            abort_if(! Storage::disk('local')->exists($path), 404);

            return Storage::disk('local')->response($path, null, [
                'Cache-Control' => 'private, max-age=3600',
            ]);
        });
    }
}
