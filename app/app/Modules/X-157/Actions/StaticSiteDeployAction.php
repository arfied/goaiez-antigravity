<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Modules\X157\Events\DeployCompleted;
use App\Modules\X157\Models\CustomDomainRequest;
use App\Modules\X157\Models\Deployment;
use App\Modules\X157\Models\EdgeZone;
use App\Services\Config\DefaultsRegistry;
use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class StaticSiteDeployAction
{
    public const KIND = 'static';

    public const MAX_BYTES = 104857600;            // seeded as sites.deploy.static_max_bytes

    public static function newHash(): string
    {
        return 'deploy_'.Str::random(16);
    }

    public function __construct(private DefaultsRegistry $defaults) {}

    public function handle(int $businessId, int $edgeZoneId, string $distDir, string $deployHash): array
    {
        return DB::transaction(function () use ($businessId, $edgeZoneId, $distDir, $deployHash) {
            $zone = EdgeZone::where('business_id', $businessId)->findOrFail($edgeZoneId);

            if (! $zone->has_valid_ssl) {
                return [
                    'status' => 'refused',
                    'refusal_code' => 'SSL_CERTIFICATE_REQUIRED',
                    'message' => 'A site cannot be published without a valid SSL certificate',
                ];
            }

            if (! preg_match('/^deploy_[A-Za-z0-9]{16}$/', $deployHash)) {
                return ['status' => 'refused', 'refusal_code' => 'BAD_HASH'];
            }

            if (Deployment::where('deploy_hash', $deployHash)->exists()) {
                return ['status' => 'refused', 'refusal_code' => 'HASH_IN_USE'];
            }

            if (! is_dir($distDir) || ! is_file($distDir.'/index.html')) {
                return ['status' => 'refused', 'refusal_code' => 'NO_INDEX'];
            }

            $max = $this->defaults->int('sites.deploy.static_max_bytes');
            $sum = 0;
            $skipped = 0;
            $acceptedFiles = [];
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($distDir, FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                $abs = $file->getPathname();
                $rel = str_replace('\\', '/', substr($abs, strlen($distDir) + 1));

                $components = explode('/', $rel);
                $valid = true;
                foreach ($components as $component) {
                    if (! preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]*$/', $component)) {
                        $valid = false;
                        break;
                    }
                }

                if (! $valid || count($components) > 8) {
                    $skipped++;

                    continue;
                }

                $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
                if (! array_key_exists($ext, ServeDeploymentAction::MIME)) {
                    $skipped++;

                    continue;
                }

                $size = (int) filesize($abs);
                $sum += $size;
                $acceptedFiles[$rel] = $abs;
            }

            if ($sum > $max) {
                return [
                    'status' => 'refused',
                    'refusal_code' => 'ARTIFACT_TOO_LARGE',
                    'bytes' => $sum,
                    'max_bytes' => $max,
                ];
            }

            $deployment = Deployment::create([
                'business_id' => $businessId,
                'edge_zone_id' => $zone->id,
                'kind' => self::KIND,
                'deploy_hash' => $deployHash,
                'status' => 'deploying',
                'speed_index' => 100,
                'speed_budget_ms' => $this->defaults->int('sites.deploy.speed_budget_ms'),
                'measured_ttfb_ms' => 0,
            ]);

            $written = 0;
            foreach ($acceptedFiles as $rel => $abs) {
                if (Storage::disk('local')->put("sites-static/{$deployHash}/{$rel}", file_get_contents($abs)) === false) {
                    throw new \RuntimeException("the site artifact could not be written: sites-static/{$deployHash}/{$rel}");
                }
                $written++;
            }

            Deployment::where('business_id', $businessId)
                ->where('kind', self::KIND)
                ->where('status', 'deployed')
                ->where('id', '!=', $deployment->id)
                ->update(['status' => 'superseded']);

            $deployment->update([
                'status' => 'deployed',
                'deployed_at' => now(),
            ]);

            $verified = CustomDomainRequest::withoutGlobalScopes()->where('business_id', $businessId)->where('status', 'verified')->orderByDesc('id')->value('domain');
            $canonicalHost = $verified !== null && $verified !== '' ? strtolower($verified) : $zone->domain_name;

            Event::dispatch(new DeployCompleted(
                businessId: $businessId,
                deploymentId: $deployment->id,
                domainName: $canonicalHost,
                deployHash: $deployHash
            ));

            return [
                'status' => 'deployed',
                'deployment_id' => $deployment->id,
                'deploy_hash' => $deployHash,
                'files' => $written,
                'bytes' => $sum,
                'skipped' => $skipped,
            ];
        });
    }
}
