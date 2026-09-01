import { rm, stat } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import path from "node:path";

const repositoryRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const buildDirectories = [
  path.join(repositoryRoot, "apps", "member-miniapp", "dist", "build"),
  path.join(repositoryRoot, "apps", "staff-miniapp", "dist", "build"),
];

for (const buildDirectory of buildDirectories) {
  try {
    await stat(buildDirectory);
  } catch (error) {
    if (error?.code === "ENOENT") continue;
    throw error;
  }

  await rm(buildDirectory, { recursive: true, force: true });
  console.log(`[miniapp-dist] removed stale development duplicate: ${buildDirectory}`);
}

console.log("[miniapp-dist] development source of truth: apps/*-miniapp/dist/dev/mp-weixin");
