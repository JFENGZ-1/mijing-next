# 无关文件夹清单

**创建日期：** 2026-07-13  
**用途：** 收纳与活跃开发无关的根目录临时文件、一次性脚本和空占位目录。移动而非删除，便于审计回溯。

| 原路径 | 现路径 | 移动原因 |
|---|---|---|
| `.tmp-catalog-smoke.png` | `无关/.tmp-catalog-smoke.png` | 根目录 DevTools 冒烟截图；不属于 `docs/generated/` 验收证据集 |
| `.tmp-eval.bat` | `无关/.tmp-eval.bat` | 一次性 `wechatide automation_evaluate` 批处理；路径含乱码、非可复用工具 |
| `.tmp-showModal-mock.js` | `无关/.tmp-showModal-mock.js` | 购卡流程 `showModal` 调试临时脚本 |
| `.tmp-showModal-result.json` | `无关/.tmp-showModal-result.json` | 上述调试的输出 |
| `parse-transcript.js` | `无关/parse-transcript.js` | 从 agent transcript 提取 revert 快照的一次性 Node 脚本；硬编码外部路径 |
| `.agents/`（空目录） | `无关/.agents-empty/` | Cursor agents 空占位目录；无项目内容 |

## 未移动（刻意保留）

| 路径 | 分类 | 原因 |
|---|---|---|
| `mijing-next/` | **CORE** | 活跃 uni-app + Laravel 系统 |
| `会员端/`、`管理端/` | **ARCHAEOLOGY** | 只读考古证据；禁止修改 |
| `docs/traceability-*.csv` | **CORE** | 追溯主账 |
| `docs/generated/` | **ARTIFACTS** | L5 脚本与 PNG 证据（活跃） |
| `docs/*.md`（考古审计） | **ARCHAEOLOGY/DOC** | L0 依据；`ARCHAEOLOGY-HANDOFF.md` 仍被引用 |
| `tools/classify-*.ps1` | **CORE** | 追溯分类脚本（**禁止** `build-traceability.ps1`） |
| `.gitignore` | **CORE** | 版本控制 |
