const fs = require("fs");
const path =
  "C:/Users/Zhong/.cursor/projects/d-Users-Zhong-Desktop/agent-transcripts/a704c810-b45d-4f27-96ad-eb3d495ac043/subagents/e1c03e0f-d333-421b-823a-156ac1226665.jsonl";
const outDir = "C:/Users/Zhong/Desktop/微信小程序原项目/revert-snapshots";
fs.mkdirSync(outDir, { recursive: true });

const targets = [
  "session-form.vue",
  "booking-policy/index.vue",
  "courses/edit.vue",
  "batch-tools.vue",
  "env.d.ts",
];

const lines = fs.readFileSync(path, "utf8").trim().split("\n");
let count = 0;
for (const line of lines) {
  const data = JSON.parse(line);
  for (const block of data.message?.content || []) {
    if (block.type !== "text" || !block.text.includes("StrReplace")) continue;
    const re = /"name":"StrReplace","input":(\{[\s\S]*?\})\}(?:,"|\})/g;
    let m;
    while ((m = re.exec(block.text)) !== null) {
      try {
        const input = JSON.parse(m[1]);
        if (!input.path || !input.path.includes("staff-miniapp")) continue;
        const rel = input.path.split("staff-miniapp/src/")[1] || input.path;
        if (!targets.some((t) => rel.includes(t.replace("index.vue", "").replace("/", "")) || rel.endsWith(t))) {
          const base = rel.split("/").pop();
          if (!targets.some((t) => t.endsWith(base))) continue;
        }
        count++;
        const safe = rel.replace(/[\\/]/g, "_");
        fs.writeFileSync(`${outDir}/${count}_${safe}_old.txt`, input.old_string || "");
        fs.writeFileSync(`${outDir}/${count}_${safe}_new.txt`, input.new_string || "");
        console.log(count, rel, "old", input.old_string?.length, "new", input.new_string?.length);
      } catch (e) {
        // ignore
      }
    }
  }
}
