import "/home/goaiez/public_html/webstudio/scripts/register-react-global.ts";
import { generateFragmentFromHtml } from "/home/goaiez/public_html/webstudio/packages/project-build/src/runtime/html.ts";
import { generateFragmentFromTailwind } from "/home/goaiez/public_html/webstudio/apps/builder/app/shared/tailwind/tailwind.ts";
import fs from "node:fs/promises";
import path from "node:path";

import { getStyleDeclKey } from "/home/goaiez/public_html/webstudio/packages/sdk/src/schema/styles.ts";
import { publishedProjectBundle, bundleVersion } from "/home/goaiez/public_html/webstudio/packages/protocol/src/schema.ts";

async function run() {
  const args = process.argv.slice(2);
  let pagesDir = "";
  let outJson = "";
  let title = "";
  let domain = "";
  
  for (let i = 0; i < args.length; i++) {
    if (args[i] === "--title") {
      title = args[++i];
    } else if (args[i] === "--domain") {
      domain = args[++i];
    } else if (!pagesDir) {
      pagesDir = args[i];
    } else if (!outJson) {
      outJson = args[i];
    }
  }

  if (!pagesDir || !outJson) {
    console.error("Usage: html-to-bundle.ts <pages-dir> <out.json> [--title <t>] [--domain <d>]");
    process.exit(1);
  }
  
  const defaultName = path.basename(path.resolve(pagesDir, ".."));
  if (!title) title = defaultName;
  if (!domain) domain = defaultName;

  const files = await fs.readdir(pagesDir);
  const htmlFiles = files.filter(f => f.endsWith(".html"));

  const bundle: any = {
    bundleVersion: bundleVersion,
    projectTitle: title,
    projectDomain: domain,
    build: {
      id: "build_1",
      projectId: "project_1",
      version: 1,
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
      pages: {
        homePageId: "page_index",
        rootFolderId: "folder_root",
        pages: [],
        folders: [{
          id: "folder_root",
          name: "Root",
          slug: "",
          children: []
        }]
      },
      breakpoints: [],
      styles: [],
      styleSources: [],
      styleSourceSelections: [],
      props: [],
      instances: [],
      dataSources: [],
      resources: [],
      assetFolders: []
    },
    page: {
      id: "page_index",
      name: "index",
      title: "index",
      path: "",
      rootInstanceId: "root_index",
      meta: {}
    },
    pages: [],
    assets: []
  };

  const instancesMap = new Map();
  
  for (const file of htmlFiles) {
    const route = file.replace(/\.html$/, "");
    const html = await fs.readFile(path.join(pagesDir, file), "utf8");
    let fragment = generateFragmentFromHtml(html);
    
    if (html.includes("<!-- @webstudio/inception/1 -->")) {
      try {
        fragment = await generateFragmentFromTailwind(fragment);
      } catch (e) {
        console.error(`Error applying Tailwind on ${file}:`);
        console.error(e);
        process.exit(1);
      }
    }

    const skippedSelectors = fragment.skippedSelectors || [];
    const instancesNames = (fragment.instances || []).map((i: any) => i.component).join(", ");
    const stylesNames = (fragment.styles || []).map((s: any) => s.property).join(", ");
    const breakpointsNames = (fragment.breakpoints || []).map((b: any) => b.label || b.id).join(", ");
    const skippedNames = skippedSelectors.join(", ");
    
    console.log(`Page ${route}:`);
    console.log(`  instances: ${fragment.instances.length} (${instancesNames})`);
    console.log(`  styles: ${fragment.styles.length} (${stylesNames})`);
    console.log(`  breakpoints: ${fragment.breakpoints.length} (${breakpointsNames})`);
    console.log(`  skippedSelectors: ${skippedSelectors.length} (${skippedNames})`);

    // add page
    const pageId = `page_${route}`;
    const rootId = `root_${route}`;
    const page = {
      id: pageId,
      name: route,
      title: route,
      path: route === "index" ? "" : ("/" + route),
      rootInstanceId: rootId,
      meta: {}
    };
    
    bundle.build.pages.pages.push(page);
    bundle.build.pages.folders[0].children.push(pageId);
    
    if (route === "index") {
      bundle.page = page;
    }
    bundle.pages.push(page);

    // add a root instance
    instancesMap.set(rootId, {
      type: "instance",
      id: rootId,
      component: "Body",
      children: fragment.children || []
    });

    // merge fragments
    for (const inst of fragment.instances || []) instancesMap.set(inst.id, inst);
    
    for (const bp of fragment.breakpoints || []) {
       if (!bundle.build.breakpoints.some((b: any) => b[0] === bp.id)) {
           bundle.build.breakpoints.push([bp.id, bp]);
       }
    }
    for (const style of fragment.styles || []) {
       const key = getStyleDeclKey(style);
       if (!bundle.build.styles.some((s: any) => s[0] === key)) {
           bundle.build.styles.push([key, style]);
       }
    }
    for (const prop of fragment.props || []) {
       bundle.build.props.push([prop.id, prop]);
    }
    for (const sss of fragment.styleSourceSelections || []) {
       bundle.build.styleSourceSelections.push([sss.instanceId, sss]);
    }
    for (const ss of fragment.styleSources || []) {
       bundle.build.styleSources.push([ss.id, ss]);
    }
  }

  for (const [id, inst] of instancesMap) {
    bundle.build.instances.push([id, inst]);
  }

  const parsed = publishedProjectBundle.safeParse(bundle);
  if (!parsed.success) {
    for (const issue of parsed.error.issues) {
      console.error(`${issue.path.join(".")}: ${issue.message}`);
    }
    process.exit(1);
  } else {
    console.log("bundle: ok (schema)");
  }

  await fs.writeFile(outJson, JSON.stringify(bundle, null, 2));

  const written = JSON.parse(await fs.readFile(outJson, "utf8"));
  console.log(`bundle: instances ${written.build.instances.length} · styles ${written.build.styles.length} · styleSources ${written.build.styleSources.length} · breakpoints ${written.build.breakpoints.length} · pages ${written.pages.length}`);
}

run().catch(e => {
  console.error(e);
  process.exit(1);
});
