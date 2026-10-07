import "/home/goaiez/public_html/webstudio/scripts/register-react-global.ts";
import { generateFragmentFromHtml } from "/home/goaiez/public_html/webstudio/packages/project-build/src/runtime/html.ts";
import { generateFragmentFromTailwind } from "/home/goaiez/public_html/webstudio/apps/builder/app/shared/tailwind/tailwind.ts";
import fs from "node:fs/promises";
import path from "node:path";

async function run() {
  const args = process.argv.slice(2);
  const pagesDir = args[0];
  const outJson = args[1];
  if (!pagesDir || !outJson) {
    console.error("Usage: html-to-bundle.ts <pages-dir> <out.json>");
    process.exit(1);
  }
  
  const files = await fs.readdir(pagesDir);
  const htmlFiles = files.filter(f => f.endsWith(".html"));

  const bundle = {
    bundleVersion: "1",
    build: {
      id: "build_1",
      projectId: "project_1",
      version: 1,
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
      pages: {
        homePage: {
          id: "page_index",
          name: "index",
          title: "index",
          path: "",
          rootInstanceId: "root_index",
          meta: {}
        },
        pages: []
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
      path: route === "index" ? "" : route,
      rootInstanceId: rootId,
      meta: {}
    };
    if (route === "index") {
      bundle.build.pages.homePage = page;
      bundle.page = page;
    } else {
      bundle.build.pages.pages.push(page);
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
       if (!bundle.build.breakpoints.some(b => b[0] === bp.id)) {
           bundle.build.breakpoints.push([bp.id, bp]);
       }
    }
    for (const style of fragment.styles || []) {
       if (!bundle.build.styles.some(s => s[0] === style.id)) {
           bundle.build.styles.push([style.id, style]);
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

  await fs.writeFile(outJson, JSON.stringify(bundle, null, 2));
}

run().catch(e => {
  console.error(e);
  process.exit(1);
});
