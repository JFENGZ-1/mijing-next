# uView Plus UI 约定

适用于 `songguo-next/apps/member-miniapp` 与 `songguo-next/apps/staff-miniapp`。

## 全局配置

- 入口：`main.ts` 中 `app.use(uviewPlus)`
- 组件：`pages.json` easycom `^u-(.*)` → `uview-plus/components/u-$1/u-$1.vue`
- 样式：`App.vue` 引入 `uview-plus/index.scss`

## 对话框

| 场景 | 推荐 |
|---|---|
| 页面内确认/成功/引导（可能连续弹出） | **`u-modal`**（`:show` + `@confirm` / `@cancel`） |
| 简单一次性系统提示、无 UI 层叠风险 | `uni.showModal` 可保留 |
| 底部多选项（3+ 平级操作） | **`u-action-sheet`** |

购卡成功等「确认 → 成功」链式弹窗优先 `u-modal`，避免微信小程序原生 `uni.showModal` 层叠失败。

## 表单与输入

- 文本/数字：`u-input`
- 结构化表单（多字段、标签对齐）：`u-form` + `u-form-item`（参考 `settings/staff/edit.vue`）
- 开关行：`u-cell` + `#right-icon` 插槽内 `u-switch`（参考 `settings/booking-policy/index.vue`）
- 单选/多选：`u-radio-group`、`u-checkbox` / `u-checkbox-group`

## 选择与列表

| 场景 | 推荐 |
|---|---|
| 枚举/目录单列选择（课程、教练、教室） | **`u-picker`**（`:columns` + `:show`） |
| 日期/时间 | 原生 `<picker mode="date|time">` 或 `u-datetime-picker` |
| 分段切换 | `u-tabs`（列表筛选）或 `u-subsection`（工具模式切换） |
| 设置/导航列表 | `u-cell-group` + `u-cell` |
| 空态/权限 | `u-empty`；加载：`u-loading-page`；提示条：`u-alert` |

## 反馈

- 轻提示：优先 **`uni.$u.toast({ message, type })`**（`type`: `default` / `success` / `error` / `warning`）
- 主操作：`u-button`（`type`、`plain`、`loading`、`size`）
- 勿在新页面混用裸 `<button>` 做主要交互（工具条小按钮除外）

## 参考页面

- 会员购卡弹窗：`member-miniapp/src/pages/cards/catalog.vue`
- 排课表单选择器：`staff-miniapp/src/pages/course/session-form.vue`
- 课程编辑：`staff-miniapp/src/pages/settings/courses/edit.vue`
- 批量课表：`staff-miniapp/src/pages/course/batch-tools.vue`
- 资料/列表：`member-miniapp/src/pages/mine/profile.vue`
