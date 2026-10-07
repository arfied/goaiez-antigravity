import { chromium } from "/home/goaiez/agents/grs-antig/app/node_modules/playwright/index.mjs";
import fs from "node:fs/promises";
import path from "node:path";
import crypto from "node:crypto";

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
  
  const assetsDir = path.join(pagesDir, "..", "assets");
  await fs.mkdir(assetsDir, { recursive: true });
  
  const manifestMap = new Map(); // url -> entry

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

      const imagesData = await page.evaluate(async () => {
        const uniqueUrls = new Set();
        for (const img of document.images) {
          if (img.currentSrc) uniqueUrls.add(img.currentSrc);
          else if (img.src) uniqueUrls.add(img.src);
        }
        
        for (const el of document.querySelectorAll('*')) {
          const bg = window.getComputedStyle(el).backgroundImage;
          const match = bg.match(/url\(['"]?(.*?)['"]?\)/);
          if (match && match[1]) {
            let u = match[1];
            if (!u.startsWith('data:')) {
              uniqueUrls.add(new URL(u, document.baseURI).href);
            }
          }
        }
        
        const results = [];
        for (const url of uniqueUrls) {
          try {
            const res = await fetch(url);
            if (!res.ok) {
              results.push({ url, error: res.statusText });
              continue;
            }
            const mime = res.headers.get('content-type') || "image/png";
            const buffer = await res.arrayBuffer();
            const bytes = new Uint8Array(buffer);
            let binary = '';
            for (let i = 0; i < bytes.byteLength; i++) {
              binary += String.fromCharCode(bytes[i]);
            }
            const base64 = btoa(binary);
            
            let width = 0, height = 0;
            let isImg = false;
            for (const img of document.images) {
              if (img.currentSrc === url || img.src === url) {
                width = img.naturalWidth;
                height = img.naturalHeight;
                isImg = true;
                break;
              }
            }
            if (!isImg) {
              await new Promise((resolve) => {
                const i = new Image();
                i.onload = () => { width = i.naturalWidth; height = i.naturalHeight; resolve(); };
                i.onerror = resolve;
                i.src = url;
              });
            }
            
            results.push({ url, base64, mime, width, height, size: buffer.byteLength });
          } catch (e) {
            results.push({ url, error: e.message });
          }
        }
        return results;
      });
      
      const toRewrite = [];
      for (const data of imagesData) {
        if (!manifestMap.has(data.url)) {
          if (data.error) {
            manifestMap.set(data.url, { url: data.url, error: data.error });
          } else {
            const hash = crypto.createHash("sha256").update(data.url).digest("hex").slice(0, 8);
            let basename = data.url.split('/').pop().split('?')[0].replace(/[^a-zA-Z0-9.\-]/g, '_');
            if (!basename) basename = "image";
            const finalName = `${hash}-${basename}`;
            
            manifestMap.set(data.url, {
              url: data.url,
              name: finalName,
              mime: data.mime,
              size: data.size,
              width: data.width,
              height: data.height
            });
            
            const buf = Buffer.from(data.base64, "base64");
            await fs.writeFile(path.join(assetsDir, finalName), buf);
          }
        }
        
        const entry = manifestMap.get(data.url);
        if (!entry.error) {
          toRewrite.push({ url: data.url, name: entry.name });
        }
      }

      await page.evaluate(({ css, toRewrite }) => {
        document.querySelectorAll('script').forEach(n => n.remove());
        document.querySelectorAll('link[rel="stylesheet"]').forEach(n => n.remove());
        
        const style = document.createElement('style');
        style.textContent = css;
        document.head.appendChild(style);
        
        for (const { url, name } of toRewrite) {
          for (const img of document.images) {
            if (img.currentSrc === url || img.src === url || img.getAttribute("src") === url) {
              img.setAttribute("src", name);
              img.removeAttribute("srcset");
            }
          }
          
          for (const el of document.querySelectorAll('*')) {
            const styleAttr = el.getAttribute('style');
            if (styleAttr && styleAttr.includes(url)) {
              el.setAttribute('style', styleAttr.split(url).join(name));
            }
          }
        }
      }, { css: styles, toRewrite });

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
  
  const manifest = Array.from(manifestMap.values());
  await fs.writeFile(path.join(assetsDir, "manifest.json"), JSON.stringify(manifest, null, 2));
  
  if (fail) process.exit(1);
}

run().catch(e => {
  console.error(e);
  process.exit(1);
});
