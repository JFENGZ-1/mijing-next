# 旧微信小程序考古交接检查点

更新时间：2026-07-11（Asia/Shanghai）

本文件只负责旧系统考古、证据和需求追溯。新系统当前实现进度见：

```text
songguo-next/docs/AI-HANDOFF.md
```

## 1. 考古目标已经改变

旧后端、旧数据库和旧业务数据已确定无法恢复。考古的目标不再是让旧系统重新连接原后端上线，而是：

1. 从旧编译产物中穷举真实存在过的业务能力。
2. 提取页面、接口、字段、状态、权限和流程证据。
3. 将每个旧页面和旧端点映射到新系统能力，确保没有静默遗漏。
4. 识别并拒绝旧系统中的后门、不安全协议和错误业务规则。
5. 把仍不确定的内容保持为 `UNREVIEWED`，不得靠猜测关闭。

旧项目是需求证据，不是要继续维护的源码，也不是新系统的数据库设计模板。

## 2. 旧产物位置与形态

旧应用目录：

- `会员端/`
- `管理端/`

两端都是 uni-app/Vue 2 编译后的微信小程序发布产物，使用 webpack、Vuex 和已停止维护的 uView UI。不存在原始 `.vue` 工程、可靠依赖清单、源码映射或后端实现。

原则：旧目录应视为只读证据。不要为了验证新系统而修改旧编译文件。

## 3. 已完成的全量静态清点

机器扫描覆盖两个旧应用的全部 JSON、WXML、WXSS、JS、WXS 和本地资源。

| 指标 | 会员端 | 管理端 | 合计 |
|---|---:|---:|---:|
| 文件 | 386 | 1379 | 1765 |
| 注册页面 | 35 | 150 | 185 |
| API 导出 | 51 | 274 | 325 |
| 应用内唯一端点 | 49 | 264 | 313 |
| 已识别调用点 | 62 | 440 | 502 |
| 后端图片资源引用 | 129 | 875 | 1004 |

确定性结论：

- 所有文本文件可读取。
- 所有 JSON 可解析。
- 185 个注册页面的 `.js/.json/.wxml/.wxss` 四件套完整。
- 49 个会员端端点和 264 个管理端端点均已从编译产物提取。
- 完整文件级 SHA-256 位于 `docs/generated/file-inventory.csv`。

限制：静态参数预览不是正式 schema；混淆变量名、服务端默认值、数据库约束和真实错误码不能仅靠前端恢复。

## 4. 考古证据索引

人工报告：

- `docs/project-audit-summary.md`：总体架构、确定缺陷、外部依赖和验证状态。
- `docs/member-audit.md`：会员端 35 页面、49 端点、字段和业务流程。
- `docs/admin-audit.md`：管理端 150 页面、264 端点、权限、报表和包体风险。
- `docs/audit-redteam.md`：对扫描误报、漏报、安全和后端契约的反审查。
- `docs/new-system-blueprint.md`：从旧能力推导出的新系统领域蓝图和处置原则。
- `docs/recovery-plan.md`：早期复原计划；其中“恢复旧数据/旧资产”的部分已被最新决策取代，只保留其业务闭环和上线门禁参考。

机器证据：

- `docs/generated/file-inventory.csv`：1765 个文件及哈希。
- `docs/generated/page-inventory.csv`：185 个注册页面。
- `docs/generated/api-catalog.csv`：API 导出、端点、读写属性和 content type。
- `docs/generated/api-usages.csv`：502 个调用点和参数上下文。
- `docs/generated/navigation-references.csv`：导航目标与缺失路由证据。
- `docs/generated/component-references.csv`：组件引用。
- `docs/generated/event-handler-references.csv`：事件绑定与疑似缺失处理器。
- `docs/generated/asset-references.csv`：本地、远程和后端资源引用。
- `docs/generated/audit-issues.csv`：扫描告警；不能直接当作真实 bug 数。
- `docs/generated/audit-summary.json`：扫描汇总。

需求追溯主账：

- `docs/traceability-pages.csv`
- `docs/traceability-apis.csv`

下一模型应优先读取追溯主账，而不是从聊天记录推测完成度。

## 5. 当前追溯矩阵状态

**考古门禁 PASS**（2026-07-11，Stage 10 收口）。`UNREVIEWED=0`。

页面矩阵共 185 行：

| Disposition | 数量 |
|---|---:|
| `KEEP` | 32 |
| `MERGE` | 60 |
| `REPLACE` | 90 |
| `REJECT` | 3 |
| `UNREVIEWED` | **0** |

API 矩阵共 313 行（310 唯一端点路径；`/common/dict`、`/wx/getUnionId`、`/wx/getWeixinPhoneNumber` 各 2 个跨端导出行）：

| Disposition | 数量 |
|---|---:|
| `MERGE` | 57 |
| `REPLACE` | 246 |
| `REJECT` | 10 |
| `UNREVIEWED` | **0** |

当前矩阵校验哈希：

```text
docs/traceability-pages.csv
SHA-256 BCD929572F74DB16D2EB94FCB7DE6D99B32AE370FE7607707CA257C5039B3A93

docs/traceability-apis.csv
SHA-256 9AD84A9A7806D5581D97E40F6917BC3BF361D9FD5A85298D0FB7F546159C9DA1
```

如果哈希变化，必须确认是有意分类更新，不能默认是格式化结果。

## 6. 已完成分类范围

### 6.1 会员端

会员端 35 个页面已全部阅读并分类。二维码页已在 Stage 10 关闭：

```text
pagesImp/QRcode/QRcode
Domain: member-code
Disposition: REJECT
```

原因：静态关注二维码，非会员身份码/核销码产品能力。

其余会员页面已映射到：

- 身份与启动路由
- 会员首页与“我的”
- 场馆详情和场馆切换
- 团课/私教预约与分享落地
- 会员资料与手机号授权
- 会员卡、权益、隐藏卡
- 订单、积分、排行、年度/月度统计
- 赠卡领卡
- 协议与隐私

会员端 49 个端点已全部分类。除一个无 token 订单查询外，旧能力均以新认证领域 API 替代。

明确拒绝（API 主账共 10 个 `REJECT`，含下列及 Stage 05–10 新增项）：

```text
API-294 /c/user/myOrderList_notoken
```

原因：一次性微信登录 code 不是订单访问授权凭证。

### 6.2 管理端会员 CRM 邻接范围

已分类 13 个管理端会员页面：会员列表、搜索、筛选、详情、资料、卡详情、积分、权益流水、充值流水、预约历史、归档卡和会员生命周期。

已分类全部 51 个 `/b/manageuser/*` 端点，覆盖：

- 会员搜索、详情、新建、编辑、批量导入
- CRM 标签、备注、拼音和动态字段
- 会员归档、恢复和访问限制
- 卡项、余额、次数、有效期和历史
- 积分、订单和预约历史
- 销售员工选择
- 潜客转登录会员

重要映射：

- `API-141 /b/manageuser/setUserNoLogin` 已映射为租户级 `appAccessStatus`，不能禁用全局 Account。
- `API-144 /b/manageuser/transferToUser` 已映射为 Stage 04 双确认潜客关联，禁止手机号自动合并。

### 6.3 额外已审管理端接口

除 `/b/manageuser/*` 外，另有 18 个管理端接口已人工分类：

```text
API-028 /b/card/findHistoryGroupName
API-030 /b/card/getAllCardInfo
API-061 /b/linkage/CalendarList
API-068 /b/linkage/getAllCardInfo
API-096 /b/mainplan/saveStaffRemark
API-156 /b/report2/addUserPoint
API-158 /b/report2/computeAgain
API-160 /b/report2/delPointLog
API-161 /b/report2/editPointLog
API-179 /b/report2/findUserAppointList
API-195 /b/report2/getSaleStaffList
API-199 /b/report2/PointListByUserId
API-213 /b/report2/substractUserPoint
API-220 /b/report2/userOrderList
API-225 /b/setting/getuserFieldSetting
API-257 /b/userorder/delUserOrder
API-259 /b/userorder/saveRemark
API-260 /b/wx/createAppCode
```

这些接口分别归入卡产品目录、日历、员工预约备注、财务重算/对账、积分不可变流水、预约历史、销售员工目录、订单事实、CRM 字段策略和小程序码分享。

## 7. 已明确拒绝复刻的旧行为

当前 API 主账中有 10 个 `REJECT`（Stage 04 及以前 4 个，Stage 05–10 新增 6 个）。核心 4 项：

1. `API-112 /b/manageuser/delUser`
   - 拒绝级联删除会员、预约和收入。
   - 用会员生命周期转换、归档和审计替代。
2. `API-160 /b/report2/delPointLog`
   - 拒绝删除积分历史。
   - 用关联原流水的冲正记录替代。
3. `API-257 /b/userorder/delUserOrder`
   - 拒绝物理删除订单和收入事实。
   - 未支付/错误单可作废；已支付订单必须退款或更正。
4. `API-294 /c/user/myOrderList_notoken`
   - 拒绝用一次性微信 code 直接查询订单。

全局还明确不继承：

- 固定测试 code 登录后门。
- URL query 中传 token。
- 全部接口强制 POST。
- 客户端通过 `dsname=slave` 控制数据库路由。
- 前端支付成功页作为支付最终事实。
- 直接编辑余额、次数、积分或历史财务事实。
- 无签名、长期有效或可重放的分享 key。
- 旧枚举数字、拼写错误和 uView 内部结构作为新领域模型。
- 编译产物中的重复接口、失效路由和 UI 库耦合。

## 8. 已确认的旧系统关键事实

### 8.1 网络协议

- 旧业务接口全部使用 POST。
- 载荷混用 JSON 和 `application/x-www-form-urlencoded`。
- 会员请求将 `deviceId` 和登录后 `tokenId` 放入 URL query。
- 查询可发送 `dsname=slave`。
- 写操作后约两秒内查询强制走主库，说明旧系统可能采用主从读写分离和短暂读后写一致性。
- 图片大量依赖 `/common/dict` 返回的 `uploadURL`，本地资源并不完整。

这些是旧行为证据，不代表新系统必须兼容。新系统已采用新的 `/api/v1` 契约。

### 8.2 业务域

旧系统能力可稳定归纳为：

- 身份、员工和权限
- 品牌、租户、场馆、教室
- 会员 CRM、标签、备注和动态字段
- 课程、教练、排课、停课、请假
- 会员卡、次数、余额、有效期和适用范围
- 团课/私教预约、候补、取消、签到、旷课和扣课
- 订单、支付、退款、充值和对账
- 积分、排行、统计和报表
- 公告、提醒和订阅消息
- 赠卡、领卡、小程序码和分享
- 系统配置、导入、导出和操作日志

### 8.3 已确认旧缺陷

- 管理端有两个被多处引用但不存在的路由：
  - `/pageReport/coach/privateDetail`
  - `/pageReport/coach/leagueDelete`
- 会员端购卡停业校验存在被强制绕过的逻辑。
- 团课分享页和领卡页各有一个确定缺失的事件处理器。
- 上传失败路径可能二次异常。
- 旧生产登录曾存在固定测试 code 可签发会话的高风险路径，新系统不得复刻。
- 生产包请求日志可能泄露 URL 中的 token。
- 管理端主包静态估算约 2.016 MiB，旧版本存在上传尺寸风险。

## 9. 自动扫描告警的正确解释

不要把 `docs/generated/audit-issues.csv` 中的告警数直接当 bug 数：

- 编译后的统一事件分发、组件 `$emit` 和 mixin 会产生缺失处理器误报。
- `MissingAssetReferences=0` 只说明扫描器把大量图片归为后端 `uploadURL`，不证明资源存在。
- 管理端 12 条无效导航实际归并到两个遗失路由。
- 参数上下文只是候选证据，不能自动生成可靠数据库 schema。

正确做法是把机器结果与 WXML、页面 JS、相邻调用点和人工报告交叉验证。

## 10. 工具脚本与高风险操作

可以重新生成机器清单：

```powershell
pwsh -File .\tools\audit-mini-programs.ps1
```

该脚本会覆盖 `docs/generated/` 下的扫描产物。运行前应确认旧应用目录没有被修改，并比较 `file-inventory.csv` 哈希变化。

严重警告：

```powershell
pwsh -File .\tools\build-traceability.ps1
```

这个脚本会从机器清单重新创建两份追溯 CSV，并把所有处置状态重置为 `UNREVIEWED`。除非先保存当前主账且明确要从零重建，否则禁止运行。

现有分类脚本：

- `tools/classify-member-traceability.ps1`
- `tools/classify-staff-member-crm-traceability.ps1`

它们只能恢复脚本内编码的会员端和管理端 CRM 分类，不能恢复额外 18 个手工审查接口。不要把它们当作完整备份。

## 11. 继续考古的标准方法

每次选择一个边界清晰的业务域，不要按文件名随机推进。

步骤：

1. 从 `traceability-pages.csv` 和 `traceability-apis.csv` 筛出该域的 `UNREVIEWED` 候选。
2. 用 `api-usages.csv` 找端点的全部调用位置。
3. 阅读页面 `.wxml/.js/.json/.wxss`，记录展示字段、输入字段、状态分支和导航参数。
4. 阅读相邻页面和复用组件，防止把报表查询误归为写业务。
5. 判断该旧能力改变哪类事实：配置、预约、权益、资金、权限或仅查询。
6. 对写接口做对抗式审查：权限、租户范围、重复提交、并发、失败回滚、不可变流水和审计。
7. 选择唯一处置：`KEEP`、`MERGE`、`REPLACE`、`REJECT` 或继续 `UNREVIEWED`。
8. 写入新能力 ID、验收用例和具体 ReviewNote；不能只填一个领域名称。
9. 更新 CSV 后重新统计，但不要重排旧 ID。
10. 若考古结论已进入新系统实现，同步更新新系统规格和阶段验收矩阵。

页面与 API 必须双向追溯：页面不能只因为新 UI 不需要就消失；接口也不能只因为旧命名难懂就合并。每次合并或替代必须说明等价业务结果。

## 12. 下一批建议考古顺序

优先顺序按新系统领域依赖和风险确定：

1. 会员卡模板、发卡、冻结、延期、余额/次数和权益流水。
2. 团课/私教排课、席位、候补、取消、签到和扣课。
3. 订单、支付、退款、充值、对账和财务报表。
4. 员工、角色、场馆范围和权限矩阵。
5. 教练请假、停课、调课及已有预约补偿。
6. 公告、订阅消息和自动提醒。
7. 积分、排行和会员统计。
8. 导入、导出、动态字段和系统配置。
9. 赠卡、领卡、小程序码和分享防重放。
10. 复杂报表与教练薪资，最后处理。

在会员卡和预约域完成前，不建议优先实现营销、排行或复杂报表。

## 13. 考古发布门禁

新系统不能因为核心流程已能运行就宣布“没有遗漏”。最终门禁：

- 185 个旧页面的 `UNREVIEWED=0`。
- 313 个旧端点的 `UNREVIEWED=0`。
- 所有 `MERGE`、`REPLACE`、`REJECT` 都有理由、新能力 ID 和验收用例。
- 两个旧遗失路由也必须有明确处置，不能从矩阵删除。
- 所有资金、权益、席位和权限条目完成对抗式审查。
- 每个新系统写接口可追溯到事务、幂等、锁、审计和测试。
- 每个报表指标可下钻到订单、资金、权益或预约事实。

旧页面数量不等于新页面数量。允许合并 UI 和 API，但不允许丢失业务结果或历史事实。

## 14. 给下一模型的考古启动指令

```text
先完整阅读 docs/ARCHAEOLOGY-HANDOFF.md、docs/project-audit-summary.md、
docs/traceability-pages.csv 和 docs/traceability-apis.csv。
旧目录“会员端/”和“管理端/”只读，不修改编译产物。
禁止运行 tools/build-traceability.ps1，因为它会清空现有人工分类。
当前门禁 **PASS**：Pages UNREVIEWED=0、APIs UNREVIEWED=0（Stage 05–10 考古规格见 `songguo-next/docs/stage-09-reporting-analytics.md` 与 `stage-10-identity-staff-tenant-cleanup.md`）。
按业务域继续考古，每个结论必须有旧文件/调用点证据、处置状态、新能力 ID、
验收用例和对抗式审查；不确定时保持 UNREVIEWED，不得猜测关闭。
同时阅读 songguo-next/docs/AI-HANDOFF.md，确保考古结论同步进入新系统规格。
```
