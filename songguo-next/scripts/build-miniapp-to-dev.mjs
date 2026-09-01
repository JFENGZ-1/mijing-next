import { createRequire } from "node:module";
import path from "node:path";
import { pathToFileURL } from "node:url";

const supportedApplications = new Set(["member-miniapp", "staff-miniapp"]);
const applicationRoot = process.cwd();
const applicationName = path.basename(applicationRoot);

if (!supportedApplications.has(applicationName)) {
  throw new Error(`Unsupported miniapp build directory: ${applicationRoot}`);
}

await import(pathToFileURL(path.join(applicationRoot, "..", "..", "scripts", "prepare-miniapp-development.mjs")));

const uniEntry = path.join(
  applicationRoot,
  "node_modules",
  "@dcloudio",
  "vite-plugin-uni",
  "bin",
  "uni.js",
);

process.env.UNI_OUTPUT_DIR = path.join(applicationRoot, "dist", "dev", "mp-weixin");
process.argv = [process.execPath, uniEntry, "build", "-p", "mp-weixin"];

console.log(`[miniapp-dist] production build output: ${process.env.UNI_OUTPUT_DIR}`);
createRequire(import.meta.url)(uniEntry);
