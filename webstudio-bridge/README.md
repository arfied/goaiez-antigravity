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
