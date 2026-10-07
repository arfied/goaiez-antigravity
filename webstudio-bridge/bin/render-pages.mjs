import { chromium } from "/home/goaiez/agents/grs-antig/app/node_modules/playwright/index.mjs";
import fs from "node:fs/promises";
import path from "node:path";

async function run() {
  const args = process.argv.slice(2);
  let base, routesFile, pagesDir, shotsDir, source;
  for (let i = 0; i < args.length; i++) {
    if (args[i] === "--base") base = args[++i];
    else if (args[i] === "--routes") routesFile = args[++i];
    else if (args[i] === "--pages") pagesDir = args[++i];
    else if (args[i] === "--shots") shotsDir = args[++i];
    else if (args[i] === "--source") source = args[++i];
  }

  if (!base || !routesFile || !pagesDir || !shotsDir) {
    console.error("Usage: node render-pages.mjs --base <url> --routes <file> --pages <dir> --shots <dir> [--source <url>]");
    process.exit(1);
  }

  const routesText = await fs.readFile(routesFile, "utf8");
  const routes = routesText.split("\n").map(r => r.trim()).filter(Boolean);

  const browser = await chromium.launch();
  const context = await browser.newContext();
  let fail = false;

  for (const route of routes) {
    const page = await context.newPage();
    let name = route.replace(/^\//, "");
    if (name === "") name = "index";

    try {
      await page.goto(base + route, { waitUntil: "networkidle" });
      
      const styles = await page.evaluate(async () => {
        const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
        const contents = [];
        for (const link of links) {
          try {
            const res = await fetch(link.href);
            contents.push(await res.text());
          } catch (e) {
            console.error("Failed to fetch", link.href, e);
          }
        }
        return contents.join('\n');
      });

      await page.evaluate((css) => {
        document.querySelectorAll('script').forEach(n => n.remove());
        document.querySelectorAll('link[rel="stylesheet"]').forEach(n => n.remove());
        
        const style = document.createElement('style');
        style.textContent = css;
        document.head.appendChild(style);
      }, styles);

      const html = await page.evaluate(() => document.documentElement.outerHTML);
      const outPath = path.join(pagesDir, `${name}.html`);
      await fs.mkdir(path.dirname(outPath), { recursive: true });
      await fs.writeFile(outPath, `<!-- @webstudio/inception/1 -->\n${html}`);

      const nameSafe = name.replace(/\//g, "-");
      
      await page.setViewportSize({ width: 1280, height: 900 });
      await page.screenshot({ path: path.join(shotsDir, `clone-${nameSafe}-1280.png`), fullPage: true });
      
      await page.setViewportSize({ width: 390, height: 844 });
      await page.screenshot({ path: path.join(shotsDir, `clone-${nameSafe}-390.png`), fullPage: true });

      if (source) {
        await page.goto(source + route, { waitUntil: "networkidle" });
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.screenshot({ path: path.join(shotsDir, `source-${nameSafe}-1280.png`), fullPage: true });
        
        await page.setViewportSize({ width: 390, height: 844 });
        await page.screenshot({ path: path.join(shotsDir, `source-${nameSafe}-390.png`), fullPage: true });
      }
    } catch (e) {
      console.error(`Failed to load or render route: ${route}`, e);
      fail = true;
    } finally {
      await page.close();
    }
  }

  await browser.close();
  if (fail) process.exit(1);
}

run().catch(e => {
  console.error(e);
  process.exit(1);
});
