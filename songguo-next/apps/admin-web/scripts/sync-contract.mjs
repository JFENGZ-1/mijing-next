import { createHash } from "node:crypto";
import { mkdir, readFile, writeFile } from "node:fs/promises";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import YAML from "yaml";

const HTTP_METHODS = ["get", "post", "put", "patch", "delete", "options", "head"];
const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const appDirectory = resolve(scriptDirectory, "..");
const openApiPath = process.env.SONGGUO_OPENAPI_PATH
  ? resolve(process.env.SONGGUO_OPENAPI_PATH)
  : resolve(appDirectory, "../../docs/openapi.yaml");
const generatedDirectory = resolve(appDirectory, "src/generated");
const baselinePath = resolve(appDirectory, "contract-baseline.json");
const accept = process.argv.includes("--accept");

function hash(value) {
  return createHash("sha256").update(value).digest("hex");
}

function groupForPath(path) {
  if (/member-cards|card-products|points/.test(path)) return "entitlement";
  if (/orders|payment|subscription/.test(path)) return "commerce";
  if (/schedule|courses|rooms|private-coaches/.test(path)) return "scheduling";
  if (/booking|appointments|check-in|waitlist/.test(path)) return "booking";
  if (/members|member-tags|crm/.test(path)) return "members";
  if (/reports|payroll|exports|reconciliation/.test(path)) return "reporting";
  if (/roles|permission|staff-directory|invites/.test(path)) return "access";
  if (/sites|chain|settings|closure|notices/.test(path)) return "organization";
  if (/auth|identity|profile|me/.test(path)) return "identity";
  return "platform";
}

function dispositionFor(operation) {
  if (operation.path.startsWith("/member/") || operation.path.startsWith("/public/")) return "IGNORE";
  if (operation.operationId === "loginWithWechat") return "IGNORE";
  if (operation.method === "GET" && (operation.path.startsWith("/staff/") || ["getHealth", "getCurrentAccount"].includes(operation.operationId))) return "ADOPT";
  if (/adjust|refund|void|cancel|promote|check.?in|freeze|transfer|payroll|reconciliation/i.test(operation.operationId)) return "CUSTOM";
  return "UNCLASSIFIED";
}

function collectOperations(document) {
  const operations = [];
  for (const [path, pathItem] of Object.entries(document.paths ?? {})) {
    for (const method of HTTP_METHODS) {
      const operation = pathItem?.[method];
      if (!operation?.operationId) continue;
      operations.push({
        operationId: operation.operationId,
        method: method.toUpperCase(),
        path,
        group: groupForPath(path),
        disposition: "UNCLASSIFIED",
        signature: hash(JSON.stringify({
          parameters: [...(pathItem.parameters ?? []), ...(operation.parameters ?? [])],
          requestBody: operation.requestBody ?? null,
          responses: operation.responses ?? {},
          security: operation.security ?? [],
        })),
      });
    }
  }

  const duplicateIds = operations
    .map((operation) => operation.operationId)
    .filter((id, index, ids) => ids.indexOf(id) !== index);
  if (duplicateIds.length) {
    throw new Error(`OpenAPI operationId 重复：${[...new Set(duplicateIds)].join(", ")}`);
  }

  return operations
    .map((operation) => ({ ...operation, disposition: dispositionFor(operation) }))
    .sort((left, right) => left.path.localeCompare(right.path) || left.method.localeCompare(right.method));
}

async function readBaseline() {
  try {
    const parsed = JSON.parse(await readFile(baselinePath, "utf8"));
    return Array.isArray(parsed) ? { sourceHash: null, operations: parsed } : parsed;
  } catch (error) {
    if (error?.code === "ENOENT") return null;
    throw error;
  }
}

function calculateChanges(previous, current) {
  const previousById = new Map((previous ?? []).map((operation) => [operation.operationId, operation]));
  const currentById = new Map(current.map((operation) => [operation.operationId, operation]));
  return {
    added: current.filter((operation) => !previousById.has(operation.operationId)).map((operation) => operation.operationId),
    changed: current.filter((operation) => previousById.has(operation.operationId) && previousById.get(operation.operationId).signature !== operation.signature).map((operation) => operation.operationId),
    removed: (previous ?? []).filter((operation) => !currentById.has(operation.operationId)).map((operation) => operation.operationId),
  };
}

const source = await readFile(openApiPath, "utf8");
const sourceHash = hash(source);
const document = YAML.parse(source);
const operations = collectOperations(document);
let baseline = await readBaseline();

if (accept || baseline === null) {
  baseline = { sourceHash, operations };
  await writeFile(baselinePath, `${JSON.stringify(baseline, null, 2)}\n`, "utf8");
}

const changes = {
  ...calculateChanges(baseline.operations, operations),
  sourceChanged: baseline.sourceHash !== null && baseline.sourceHash !== sourceHash,
};
const counts = { ADOPT: 0, CUSTOM: 0, IGNORE: 0, UNCLASSIFIED: 0 };
for (const operation of operations) counts[operation.disposition] += 1;

const publicOperations = operations.map(({ signature, ...operation }) => operation);
const report = {
  generatedAt: new Intl.DateTimeFormat("zh-CN", { dateStyle: "medium", timeStyle: "medium", timeZone: "Asia/Shanghai" }).format(new Date()),
  sourceHash,
  total: operations.length,
  counts,
  changes,
};

const generatedSource = `import type { ApiOperationSummary, ContractReport } from "@/types/admin";\n\nexport const apiOperations: ApiOperationSummary[] = ${JSON.stringify(publicOperations, null, 2)};\n\nexport const contractReport: ContractReport = ${JSON.stringify(report, null, 2)};\n`;

await mkdir(generatedDirectory, { recursive: true });
await writeFile(resolve(generatedDirectory, "api-contract.ts"), generatedSource, "utf8");
await writeFile(resolve(generatedDirectory, "contract-report.json"), `${JSON.stringify(report, null, 2)}\n`, "utf8");

console.log(`契约同步完成：${operations.length} 个操作，ADOPT ${counts.ADOPT}，CUSTOM ${counts.CUSTOM}，IGNORE ${counts.IGNORE}，UNCLASSIFIED ${counts.UNCLASSIFIED}`);
console.log(`变化：新增 ${changes.added.length}，变更 ${changes.changed.length}，删除 ${changes.removed.length}，源文档 ${changes.sourceChanged ? "已变化" : "一致"}`);
