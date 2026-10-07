# Webstudio Bridge

This directory contains the integration scripts between the cloner output and Webstudio.
The coder never runs `run-clone.sh` for real.

`template-cache.sh` manages a pinned, idempotent clone of the website cloner template, running `npm ci` with Node 24.
`html-to-bundle.ts` converts a directory of HTML pages into a Webstudio bundle JSON format that can be imported.
`render-pages.mjs` renders the React pages into static HTML with inlined CSS using Playwright, and takes screenshots.
`run-clone.sh` is the main pipeline script that creates the workspace, sets up the template and MCP, runs the LLM, renders the pages statically using Playwright, generates the bundle, optionally imports it, and writes the final report.
`check.sh` is the verification script that runs syntax checks, tests, and a dry-run of the long pipeline.

To run the long clone pipeline, the owner or supervisor uses the following command:
`nohup bash webstudio-bridge/bin/run-clone.sh <url> <business_id> > .agents/supervisor/.clone-w0.log 2>&1 &`

## Publishing (W4a)

The static publishing pipeline converts a Webstudio project into static HTML and assets.
It is built on a fast, warm dependency and CLI cache:
- `bin/webstudio-cache.sh` creates the two cache directories.
- `cli.lock` pins the CLI version.
- The vendored lockfile pair (`locks/ssg-*.package.json` and `locks/ssg-*.package-lock.json`) pins the template dependencies.

The publish operation has three scripts and steps:
- `bin/publish-static.sh`: the orchestrator script with its step lines (cache, workdir, link, sync, template, scaffold, deps, build, flatten, done).
- `bin/publish-template.sh`: copies the template and sets the base path dynamically in `vite.config.ts` and `app/constants.mjs`.
- `bin/publish-flatten.sh`: removes the framework-specific `dist/server` artifacts and hoists the `dist/client/sites/{b}/{hash}/assets` to `dist/client/assets` when using a non-root base.

**The Base Path Rule**: One build serves both the platform address `/sites/{b}/{hash}/` and a custom domain root, because the custom-domain middleware passes unknown paths through to the host-agnostic platform routes.

**The Hard-Won Rules**:
1. Every external call reads stdin from `/dev/null` (a background process group that reads its terminal is stopped).
2. The scaffold names the `ssg` alias before our folder (`--template ssg --template "$TPLDIR"`) — the alias selects the framework.
3. The template folder is a sibling of the project and is removed after the scaffold (vike scans every `+*.ts(x)` under the project).

**Unmeasured items**:
- Image assets on publish (no project with images exists yet).
- Multi-page exports.
