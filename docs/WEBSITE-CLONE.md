# Website Clone Pipeline

## What it is

The website clone feature accepts a URL and orchestrates a full extraction into an editable Webstudio project. The user submits a URL, the backend triggers an automated extraction flow using Claude and Playwright, bundles the resulting assets, imports them into a Webstudio editor session, and exposes a publish pathway. 

## Moving parts

- **Clone Side**: `App\Services\SiteClone\SiteCloneJobs` initiates the process, dispatching `RunSiteCloneJob` onto the `clone` queue. The job invokes `webstudio-bridge/bin/run-clone.sh`, which runs `claude -p` equipped with a Playwright MCP. The resulting artefacts are bundled by `html-to-bundle.ts` and `project.mjs`, then pushed via `webstudio import`.
- **Publish Side**: `App\Services\Webstudio\WebstudioSites` and `PublishWebstudioSiteJob` govern the deployment. They execute `publish-static.sh`, and `StaticSiteDeployAction` manages the static artefact deployment to `/sites/{business}/{hash}/` along with mapped and verified custom domains.

## Configuration

- `config/site_clone.php` parameters map to their corresponding environment variables.
- Platform Credentials: `anthropic_api_key` and `webstudio_auth_secret` must be provided in Ops → Credentials.
- Platform Settings: `sites.clone.max_concurrent`, `sites.clone.timeout_seconds`, and `sites.deploy.static_max_bytes` control concurrency, execution limits, and artefact sizes.

## Directories

- `~/public_html/clones/{business}/{slug}`: holds the job execution context. It uses an Apache `Require all denied` guard.
- `~/.cache/goaiez-cloner` and `~/.cache/goaiez-webstudio`: local build caches.
- `storage/app/private/sites-static/{hash}`: stores the published static site outputs.

## Scheduler

- A dedicated `queue:work --queue=clone` worker continuously polls for extraction jobs.
- `site-clone:prune-evidence` is scheduled daily to sweep clone execution directories older than the 7 day retention period. 
- No crontab changes are necessary.

## Deploying

To deploy the clone subsystem:
1. Run the three specific migrations.
2. Execute `composer deploy`.
3. Warm the caches once (run as the app user): `bash webstudio-bridge/bin/webstudio-cache.sh` and `bash webstudio-bridge/bin/template-cache.sh`.
4. Apply the builder patch: run `patches-check.sh` and if successful, `patches-apply.sh`.
5. Set `SITE_CLONE_BUILDER_ORIGIN` in the environment to the public builder hostname.

## Runbook

- **Clone stuck `running`**: Marked as interrupted on the next screen read if 120 seconds elapse without a heartbeat. Its directory is removed.
- **Publish stuck**: The `recoverStale` process cleans it up after 20 minutes.
- **Builder down**: Publishes will fail displaying `Publishing failed`.
- **Re-clone the same host**: The execution directory is replaced idempotently before the job begins.

## Limits, measured

- The system currently extracts one route per clone request.
- Based on ledger data, a flat page extraction using `claude-opus-5-5` takes approximately 10 minutes and costs ~$3.55.
- Image assets on publish and multi-page exports remain unmeasured.
- Editor links strictly require the public hostname to function.
