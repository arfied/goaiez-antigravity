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

            // output_media_url usually stores the URL itself, but for the actual image path, we might need to store a separate column or deduce it.
            // Wait, the brief says: "output_media_url = a temporary signed URL ... write JPEG to branded/{businessId}/{md5(source)}-{destination}.jpg"
            // Wait, does BrandedMedia have a column for the local path, or is it just output_media_url?
            // "output_media_url = a temporary signed URL to a NEW route x-189.media"
            // If output_media_url is the URL, where do we store the local path? Let's check `BrandedMedia` model.
            // Actually, we can re-derive the path in the controller from the `source_asset_url` and `destination`!
            $path = 'branded/'.$business.'/'.md5($row->source_asset_url).'-'.$row->destination.'.jpg';

            abort_if(! Storage::disk('local')->exists($path), 404);

            return Storage::disk('local')->response($path, null, [
                'Cache-Control' => 'private, max-age=3600',
            ]);
        });
    }
}
