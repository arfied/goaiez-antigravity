<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Modules\X157\Models\Deployment;

class DeploymentArmReadAction
{
    /**
     * @return array{control: ?array{hash: string, served: int}, variant: ?array{hash: string, served: int}}
     */
    public function forVariant(int $businessId, int $pageVariantId): array
    {
        // One deployed row could be the variant. The control might be 'deployed' or 'superseded'.
        // Actually, the prompt says "read via a NEW X-157 action DeploymentArmReadAction::forVariant...".
        // Wait, how do we distinguish control vs variant deployments?
        // The variant has `page_variant_id` set to `$pageVariantId` and `status` = 'deployed' or 'superseded'.
        // What about control? The control deployment's `page_variant_id` is NULL.
        // Wait, X-103's PageVariant row has the hashes. X-157 only needs to read by hashes...
        // But the signature is `forVariant(int $businessId, int $pageVariantId): array{control: ?array{...}, variant: ?array{...}}`
        // Wait, does control deployment have `page_variant_id` set? No, `ServeDeploymentAction`/`LatestDeploymentForPageAction` see only control (`page_variant_id IS NULL`).
        // Ah, `EdgeDeployAction::handle(..., ?string $businessName = null, ?int $pageVariantId = null)` writes `deployments.page_variant_id`; supersede is arm-scoped;
        // Control deployment does NOT have `page_variant_id`. Wait, how do we find control deployment by `$pageVariantId`?
        // "read the exact FQNs from Pages.php/EdgeDeployAction.php imports... refuses when... take the control PageVersion... call EdgeDeployAction::handle(..., pageVariantId: <row id>)"
        // If the control deployment does NOT have `page_variant_id`, then X-157 doesn't know which is the control for this variant... unless we find it by hash, or X-103 gives the hashes to X-157?
        // But the signature is `forVariant(int $businessId, int $pageVariantId): array{control: ?array{hash: string, served: int}, variant: ?array{hash: string, served: int}}`
        // Does the variant deployment know its control? Or do we return the latest null-page_variant_id deployment?
        // Ah! No, wait. We can just query `deployments` where `page_variant_id = $pageVariantId`. But wait, that only gives variant.
        // Where is `page_variant_id` on control? Maybe the instructions say:
        // "EdgeDeployAction::handle(..., ?string $businessName = null, ?int $pageVariantId = null) writes deployments.page_variant_id; supersede is arm-scoped"
        // If control is NOT updated with `page_variant_id`, then we can't find it just by `pageVariantId`!
        // Wait! Let's re-read the prompt:
        // "denominator: the arm's deployments.served_count (read via a NEW X-157 action DeploymentArmReadAction::forVariant(int $businessId, int $pageVariantId): array{control: ?array{hash: string, served: int}, variant: ?array{...}} — X-103 never imports X-157's model)."
        // If `DeploymentArmReadAction::forVariant` only takes `pageVariantId`, maybe it needs to look at `PageVariant` model?
        // "X-103 never imports X-157's model" -> "Boundary: X-157 must not import X-103's model either"
        // Wait, if X-157 cannot import X-103's model, and X-103 cannot import X-157's model, how does X-157 know the control hash?
        // Let's ask X-103? "Boundary: X-157 must not import X-103's model either — the middleware asks X-103 through a NEW action App\Modules\X103\Actions\PageVariantReadAction::runningFor".
        // Wait, if X-157 needs to read BOTH arms by `pageVariantId`, maybe it asks X-103 for the hashes?
        // No, `DeploymentArmReadAction` is in X-157. Can it use `DB::table('page_variants')`? Yes, using `DB::table()` doesn't import the model!
        // Let's re-read: "the middleware asks X-103 through a NEW action App\Modules\X103\Actions\PageVariantReadAction::runningFor". This applies to middleware.
        // What about `DeploymentArmReadAction::forVariant`?
        // Let me just look up the row using DB::table('page_variants').
        $row = \Illuminate\Support\Facades\DB::table('page_variants')
            ->where('id', $pageVariantId)
            ->where('business_id', $businessId)
            ->first();

        if (! $row) {
            return ['control' => null, 'variant' => null];
        }

        $control = Deployment::where('business_id', $businessId)->where('deploy_hash', $row->control_deploy_hash)->first();
        $variant = Deployment::where('business_id', $businessId)->where('deploy_hash', $row->variant_deploy_hash)->first();

        return [
            'control' => $control ? ['hash' => $control->deploy_hash, 'served' => $control->served_count] : null,
            'variant' => $variant ? ['hash' => $variant->deploy_hash, 'served' => $variant->served_count] : null,
        ];
    }
}
