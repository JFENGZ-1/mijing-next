# 管理端微信小程序全量审计报告

> 审计对象：`管理端/`（前同事遗留的编译后微信小程序产物）  
> 审计日期：2026-07-11  
> 原则：只读审计，不修改业务源码；自动扫描结果均经过配置、关键页面和打包运行时代码抽样核验。

## 1. 结论先行

该目录保存的是一个可被微信开发者工具识别的 **uni-app/Vue 2 编译产物**，不是原始可维护工程。它包含完整的页面四件套和打包运行时，因此“打开并浏览界面”有较高概率可行；但后端、原始 `src`、依赖清单、构建配置、环境变量、接口文档、数据库和部署配置均已遗失，不能据此直接恢复生产业务。

当前至少有以下上线阻断项：

1. **后端完全缺失。** 生产 API 固定为 `https://interface.songguoyueke.com/api`，管理端共发现 274 个 API 导出、264 个唯一端点、440 个调用点。没有兼容后端时，登录后绝大多数页面不可用。
2. **主包体积越界风险。** 静态盘点主包约 2.016 MiB（2,114,098 字节），已经高于常见 2 MiB 主包上限；`app.json` 内嵌了五组 tabBar base64 图标，是明显膨胀源。必须用当前微信开发者工具实际预览/上传复核。
3. **身份体系无法独立恢复。** 登录依赖 `wx.login` code、设备 UUID、服务端 `/b/staffuser/wxlogin?deviceId=`、服务端下发 `tokenId`、站点和权限列表。仅复制前端无法伪造完整权限语义。
4. **明确存在失效页面跳转。** 6 个文件共 12 次跳转指向未在 `app.json` 注册、磁盘也未发现的 `pageReport/coach/privateDetail` 和 `pageReport/coach/leagueDelete`。
5. **支付、手机号解密、二维码、上传均依赖后端或微信后台配置。** 商户号/支付证书、服务器域名白名单、隐私协议、手机号解密、二维码生成等资料缺失。
6. **这不是可持续维护的源码。** `common/vendor.js` 单文件约 645 KB，集中了 Vue、Vuex、uView、请求封装、全部 API 定义和环境配置；变量已压缩，缺少 source map 与源码目录。直接在产物上长期开发风险很高。

建议把当前代码视为“行为规格与 UI 取证样本”，而不是最终复活工程。正确路线是：先冻结产物并建立接口契约，再恢复后端兼容层，随后重建可编译源码工程。

## 2. 审计范围与完整性

- 文件总数：1379。
- 文本文件：1355；自动读取错误 0；JSON 解析错误 0。
- 主要类型：342 `.wxss`、340 `.js`、337 `.json`、334 `.wxml`、13 `.png`、2 `.wxs`。
- 注册页面：150；页面四件套缺失 0。
- 组件引用：1774；静态扫描未发现缺失组件。
- 事件绑定：1707；发现 33 个疑似缺失处理器。
- 内部导航：发现 12 个未注册目标。
- API：274 个导出、264 个唯一端点、440 个业务调用点。
- 后端资源引用：875 处；远程 URL 引用至少 9 处。

配套的穷举清单位于：

- `docs/generated/file-inventory.csv`：逐文件清单。
- `docs/generated/page-inventory.csv`：全部 150 个注册页面及完整性。
- `docs/generated/component-references.csv`：全部组件引用。
- `docs/generated/navigation-references.csv`：全部跳转及注册状态。
- `docs/generated/event-handler-references.csv`：全部事件绑定和处理器匹配。
- `docs/generated/api-catalog.csv`：**全部 274 个 API 导出，含方法、端点、Content-Type、读写性质和源文件。**
- `docs/generated/api-usages.csv`：**全部 440 个调用点，含调用文件、行号和参数上下文；这是现阶段恢复请求参数/响应字段最完整的机器可读依据。**
- `docs/generated/asset-references.csv`：本地与后端静态资源引用。

对抗式说明：静态扫描只能证明字符串和绑定关系，不能证明运行时分支一定可达。特别是 Vue 编译产物中的事件代理、动态组件、拼接 URL 可能产生误报或漏报；因此报告将确定性问题与待真机复核项分开。

## 3. 技术架构

### 3.1 工程形态

- 微信小程序 AppID：`wx144915b575a7792d`。
- 开发者工具基础库：`3.16.1`（来自 `project.private.config.json`）。
- 标题/品牌：松果约课。
- 构建来源特征：uni-app + Vue 2 + Vuex 3.6.2 + uView UI。
- 入口 `app.js` 只加载 `common/runtime.js`、`common/vendor.js`、`common/main.js`。
- `common/vendor.js` 包含框架、请求库、store、API 模块和配置；页面 `.js` 是 webpack chunk。
- `project.config.json` 开启上传 source map，但仓库中未见可用 source map；`es6/postcss/minified` 等编译选项反映的是产物调试配置，不是原始构建工程。

缺失：`package.json`、`src/`、`manifest.json`、uni-app `pages.json`、锁文件、Babel/Vite/HBuilderX 配置、环境文件、测试、CI/CD、README、API schema。

### 3.2 分包与功能域

| 包 | 约大小 | 主要功能 |
|---|---:|---|
| 主包 | 2.016 MiB | 启动、今天、课程、会员、报表、场馆、登录、场馆授权、商城订单、头像裁剪 |
| `pageReport` | 1.508 MiB | 营业/课程报表、提醒、会员分析、排行榜、教练与薪资统计、连锁统计 |
| `pagesImp` | 1.104 MiB | 场馆资料、员工、卡种、课程/科目、授权、二维码 |
| `pageMember` | 0.850 MiB | 会员搜索筛选、详情、卡/积分/消费/充值/课程记录、删除会员/卡 |
| `pagesCourse` | 0.633 MiB | 排课、课程选择、课表管理、私教/团课详情、场地 |
| `pageChain` | 0.545 MiB | 连锁品牌、门店、员工、课程/卡统计、连锁卡种 |
| `pageConfig` | 0.490 MiB | 预约、会员、协议、支付、报表、停课、请假、提醒、通知配置 |
| `pageServer` | 0.078 MiB | 服务购买、协议、订单、视频帮助 |

主导航五栏：今天、课程、会员、报表、场馆。启动页完成自动登录/站点选择后进入主业务。

## 4. 状态、登录、授权与权限

### 4.1 本地状态

Vuex store 持久化或使用的关键键包括：

- `UUID`：首次运行随机生成并写入 storage，作为 `deviceId`。
- `token`：服务端返回的 `tokenId`。
- `userInfo`、`shopInfo`、`stopInfo`、`logonUserInfo`。
- 运行态还包含 `userFuncList`、`permissionInfo`、`staffUserid`、`site`、软件过期状态、最近写操作时间等。

安全判断：token 使用普通微信本地 storage 保存；这在小程序中常见，但不能当作可信授权边界。后端必须对每个写接口重新校验 token、站点、员工角色和资源归属，不能信任前端隐藏按钮或 `funcId`。

### 4.2 登录链路

1. 启动时调用 `wx.login` 获取 code。
2. 读取/生成 UUID，并把 `deviceId` 自动附加到请求 query。
3. 调用 `POST /b/staffuser/wxlogin?deviceId=`，请求体包含 `code` 等启动参数。
4. 服务端成功响应至少包含：`code`、`funcList`、`site`、`hasSiteCount`、`isVisitor`、`isLinkSite`、`tokenId`、`staffType`、`staffUserId`。
5. 前端清理旧登录态，写入权限列表、站点信息、登录员工信息和 token。
6. 后续请求拦截器把 `deviceId`、`tokenId` 放入 URL query，并从 body 剔除同名字段。

异常点：开发环境存在用固定字符串（如 `test005`）替代真实 login code 的分支；当前环境常量硬编码为 `production`，但重建工程时必须彻底移除此类测试后门，防止编译环境错误导致共用测试身份。

### 4.3 权限

- 前端通过 `userFuncList` 与 `hasPermission`/`getUserFunc` 控制功能显示。
- 登录后还会依据站点数量、访客、连锁门店、员工类型等分流。
- `funcId` 的完整业务字典仅后端可权威恢复；前端可提取已使用编号和页面入口，但无法证明未出现的权限不存在。
- 后端重建时必须采用“默认拒绝”，按接口建立角色/资源授权矩阵。

### 4.4 微信授权

发现两套相似授权页：主包 `pages/shop/authorizationPage/*` 与分包 `pagesImp/authorization/*`，均涉及头像昵称和手机号。手机号流程依赖 `/wx/getWeixinPhoneNumber` 或相关服务端解密接口，必须恢复正确 AppID/AppSecret 与当前微信手机号组件协议。隐私组件调用 `getPrivacySetting`/`openPrivacyContract` 等能力，上线前需在微信公众平台重新配置隐私保护指引，不能只保留前端弹窗。

## 5. 网络层与 API 总览

### 5.1 环境和域名

打包配置模块固定：

- 生产 API：`https://interface.songguoyueke.com/api`
- 测试 API：`https://test.songguoyueke.com/api`
- 关联开放小程序 AppID：`wxb72f22dbc5dfba4c`
- 另有硬编码测试图片：`https://test.songguoyueke.com/upload/imgs/triangle_02.png`

当前常量 `n="production"`，因此产物默认走生产域名。上线恢复必须确认域名所有权、HTTPS 证书、ICP备案、微信 request/upload/download 合法域名白名单和 CORS/防盗链策略。若旧域名已不受控制，应立即更换，不能继续信任 DNS 指向。

### 5.2 请求协议

- 几乎全部为 POST，即使查询也使用 POST。
- Content-Type 主要为 `application/x-www-form-urlencoded` 或 `application/json`。
- 请求拦截器统一追加 `deviceId`；有 token 时追加 `tokenId`。
- 写请求会记录最近写入时间；短时间内的后续读取可能加 `dsname=slave`，显示旧系统可能有主从库读写路由。
- 成功通常以业务 `code == 200` 判断，错误信息使用 `msg`；响应主体常从 `data`、`list`、`rows`、`url`、`sharekey` 等字段读取。
- 网络层只以 HTTP 200 进入业务响应；必须在新后端明确 HTTP 状态码、业务码、超时、幂等和重试约定。

### 5.3 全部 API 分类

全部端点逐条见 `docs/generated/api-catalog.csv`，全部参数/响应使用上下文见 `docs/generated/api-usages.csv`。按域统计如下：

| 域 | 唯一导出数量级 | 职责 |
|---|---:|---|
| `/b/report2` | 66 | 营业、课程、会员、排行、薪资、连锁等报表 |
| `/b/manageuser` | 53 | 会员、会员卡、充值消费、标签、积分、二维码分享等 |
| `/b/arrange` | 28 | 排课、调课、停课、签到、预约状态 |
| `/b/linkage` | 24 | 连锁门店、跨店数据、连锁卡/员工/课程统计 |
| `/b/card` | 16 | 卡种定义、卡面、卡组、状态 |
| `/b/course` | 15 | 团课/私教、场地、标签、教练、科目 |
| `/b/staff` | 14 | 员工、角色、邀请、入离职 |
| `/b/mainplan` | 13 | 主排课计划、团课计划 |
| `/b/staffuser` | 11 | 登录、当前用户、站点配置、今日数据、退出 |
| `/b/setting` | 7 | 预约、提醒、显示等业务设置 |
| `/b/platform` | 7 | 套餐/服务订单、微信支付下单 |
| `/b/site` | 3 | 场馆资料、营业时间 |
| `/b/export` | 3 | 数据/课表导出 |
| `/b/userorder` | 3 | 用户预约订单 |
| 其他 | 4 | `/b/wx`、`/common/const`、`/common/dict`、`/wx/*` |

接口契约恢复限制：前端能确定“发送了哪些字段”和“读取了哪些响应字段”，不能确定数据库类型、必填性、枚举全集、默认值、事务边界和服务端校验。重建时应从 440 个调用点生成 OpenAPI 草案，然后逐页面回放验证；不得把前端未校验等同于后端可选。

## 6. 关键平台能力

### 6.1 支付

- `pageServer/order.js` 涉及服务套餐订单和 `requestPayment`。
- API 包含 `/b/platform/submitwexinOrder` 等平台订单接口。
- 前端期望服务端返回微信支付参数（典型字段包括 `timeStamp`、`nonceStr`、`package`、`signType`、`paySign`）。
- 缺失项：商户号、API v3 key、商户证书、平台证书、回调 URL、订单表、签名与验签、退款、对账、幂等、支付结果主动查询。
- 阻断结论：前端支付按钮存在不代表支付可用；必须重新完成微信支付商户绑定和服务端支付闭环。

### 6.2 分享与二维码

- 多个页面实现 `onShareAppMessage`，员工邀请页 `pagesImp/shop/staff/invited-share.js` 生成邀请分享。
- `components/cardToolbox/getCard/index.js` 调用 `/b/wx/createAppCode` 取得 `url`，调用 `/b/manageuser/getShareKey` 取得 `sharekey`，用于领卡/会员卡分享链路。
- `pagesImp/QRcode/QRcode` 是二维码展示页。
- 分享路径中的 `sharekey` 必须服务端短期有效、单用途、可撤销并绑定业务对象；不可仅靠可猜 ID。
- 小程序码需要当前 AppID 的有效 access_token，由服务端调用微信 API 生成；旧二维码 URL 和旧分享 key 均不能假定继续有效。

### 6.3 上传、裁剪与下载

- 场馆、员工、会员资料等页面使用 `chooseImage`/上传；头像裁剪器基于 WeCropper 1.3.9。
- `pagesCourse/index/components/download-timetable.js` 涉及课表图片/文件下载和保存相册。
- 上传最终依赖请求层的 `UPLOAD`/`wx.uploadFile` 与后端文件服务，资源 URL 大量由后端返回。
- 必须恢复：上传接口、鉴权、大小/MIME/扩展名校验、图片解码重编码、对象存储、CDN、病毒/恶意文件检查、私有资源授权和生命周期清理。
- 不能信任客户端文件名、Content-Type 或图片后缀。

### 6.4 隐私与用户信息

- 存在自定义 `components/privacy`。
- 涉及手机号、头像、昵称、会员资料、生日、消费记录、课程记录、员工信息和薪资数据，属于高敏感业务数据。
- 上线需要最小化采集、访问审计、删除/更正机制、数据保留周期、管理员权限分级和隐私政策一致性。

## 7. 静态资源

仓库内仅 13 个 PNG，主要是启动图、提示图和 tabBar 图标。大量课程图片、头像、卡面、场馆图片、二维码、报表导出结果由后端 URL 提供，扫描到 875 处后端资源引用。因此即使 API 返回模拟 JSON，若文件服务未恢复，界面仍会大量缺图。

`app.json` 直接内嵌五组 tabBar 的 base64 `iconData/selectedIconData`，同时磁盘又保留对应 tabbar PNG，形成重复与主包膨胀。重建源码时应只保留文件路径版本并核对微信当前 tabBar 规范。

确定的环境污染：`pagesImp/card/components/HM-dragSorts/HM-dragSorts.wxml` 硬编码测试域图片 `triangle_02.png`，生产发布前必须替换为本地或生产 CDN 资源。

## 8. 确定缺陷与疑似缺陷

### 8.1 确定：失效导航

以下目标未在 `app.json` 注册，磁盘也未找到对应页面：

- `pageReport/coach/privateDetail`
- `pageReport/coach/leagueDelete`

调用源共 6 个：

- `pageChain/courseStatistics/detailed.js`
- `pageReport/coach/detailed.js`
- `pageReport/course/courseReportFormDay.js`
- `pageReport/teacherMembership/detailed.js`
- `pageReport/teacherMembership/memberShipSalaryDetail.js`
- `pageReport/teacherMembership/personalSalaryDetail.js`

每个源文件各有两个目标分支，共 12 处。进入相应详情/删除操作会跳转失败。这很可能是丢失页面或迁移后未改路径，必须从旧版本、体验版或后端菜单配置中找回。

### 8.2 待真机复核：33 个事件处理器

静态扫描发现 33 个 WXML 绑定无法在同名页面脚本中找到直接方法，包括：

- 首页：`editremarkOrder`、`confirm`。
- 会员：`moreProject`、`appointDetails`、多个 `moreClick`。
- 报表：`appointShowDrop`、`headleDetails`、`showDrop`、`cancelAppointment`、`truant`、`editAppointment`、`remark`。
- 连锁：`saveWeekTime`、`setTeamList`、`memberDetails`、`changeDate` 等。
- 课程/科目：`closeweek`、`headleClose`。

完整逐条位置见 `docs/generated/audit-issues.csv`。这些不应全部直接判为 bug：部分可能由 Vue mixin、父组件透传、编译事件代理或条件分支提供。但首页和关键操作必须逐项真机点击并观察控制台；若出现 `is not a function` 或无响应，即为上线阻断。

### 8.3 其他风险

- 拼写不一致大量存在：`suject`、`compontents`、`persion`、`selecct`、`headle`、`Balace`、`wexin`、`Menber`、`mumber`。后端兼容层必须保留既有错误拼写，否则会破坏协议；新接口应另行规范并提供映射。
- 同类功能在主包、`pagesImp`、`pageChain` 中有复制版本，可能已产生行为分叉。
- 所有 API 多用 POST，读写语义依靠自定义 `isQuery/isWrite`，不利于缓存、审计与网关策略。
- 请求参数被拼到 URL 的 `tokenId` 可能进入代理、服务器访问日志和监控系统，增加凭据泄露面；新后端应迁移到 `Authorization` header，并设短期令牌和轮换。
- 本地 `urlCheck=false` 只影响开发者工具，不能替代微信后台合法域名配置。
- 未见 `sitemap.json`；应由当前微信工具和审核规则确认是否必须补齐及索引策略。
- 未见自动化测试、错误上报、性能监控、埋点字典和发布回滚机制。

## 9. 后端重建的最小领域模型

根据页面和接口，可推导至少需要以下领域；这只是前端可见下界：

- 账号与租户：微信身份、员工账号、角色、权限、站点、连锁品牌、跨店关系。
- 场馆：基础资料、营业时间、教室/场地、配置、软件服务期限。
- 课程：团课、私教、科目、标签、教练、开放规则、排课计划、课次、停课/调课。
- 会员：档案、标签、积分、访客状态、生日与提醒。
- 会员卡：卡种、卡面、次数/时长/余额、适用课程、有效期、停卡、转卡、补卡、共享/领取。
- 预约履约：预约、排队、签到、旷课、取消、退款状态、备注。
- 资金：售卡、充值、消费、退款、支付订单、服务套餐、对账。
- 员工：邀请、入职、离职、角色、课程权限、薪资规则和统计。
- 报表：营业、课程、会员、排行榜、教练、薪资、连锁聚合。
- 配置与通知：预约规则、提醒、展示字段、会员协议、通知管理。
- 文件：头像、场馆图、卡面、小程序码、导出文件。

所有表至少需要 `tenant/site` 隔离字段、审计字段、软删除/状态字段、幂等键和必要的版本号。资金、卡余额、课次扣减与退款必须使用事务和不可变流水，不能只更新汇总余额。

## 10. 恢复上线执行顺序

### P0：先证明资产归属

1. 确认 `wx144915b575a7792d` 的管理员权限、主体、开发者、体验版和历史版本。
2. 确认两个 API 域名、服务器、DNS、证书、对象存储、CDN、微信支付商户的所有权。
3. 从微信后台下载最近线上代码/体验版信息，寻找旧 Git、HBuilderX 工程、CI 制品、source map、云服务器快照和数据库备份。
4. 若无法证明旧域名和 AppID 安全可控，立即规划新 AppID/域名迁移，撤销旧密钥。

### P1：建立可验证基线

1. 用微信开发者工具导入 `管理端/`，记录编译错误、包体积、基础库兼容性。
2. 在隔离测试环境用 mock server 实现登录与只读基础接口，使 150 个页面可遍历。
3. 对照 `api-catalog.csv` 和 `api-usages.csv` 生成 OpenAPI 初稿，逐接口标注请求字段、读取的响应字段、枚举和调用页面。
4. 建立页面冒烟矩阵，覆盖 5 个 tab、全部分包、33 个疑似事件和 12 个失效导航。

### P2：恢复核心闭环

优先顺序建议：登录/租户/权限 → 场馆与员工 → 课程与排课 → 会员与会员卡 → 预约签到扣课 → 支付/退款 → 报表/薪资/连锁。每个闭环必须同时具备后端鉴权、审计、迁移脚本和自动化测试。

### P3：重建源码工程

以当前 UI 和行为为参照，新建可编译的 uni-app 或原生小程序工程；不要继续把 webpack 产物当源代码。保留旧接口兼容适配层，逐页面迁移，并通过抓包/快照/业务用例对比。迁移完成前冻结本目录作为证据，不做格式化或批量反编译覆盖。

### P4：上线前硬门槛

- 微信合法域名、隐私指引、用户信息与手机号能力审核通过。
- 主包与各分包体积达标；低端机性能、首屏、内存和弱网测试通过。
- 支付签名、回调、退款、对账和幂等通过沙箱/小额真单验证。
- 权限越权、租户穿透、IDOR、文件上传、分享 key、token 日志泄露等安全测试通过。
- 数据备份恢复演练、监控告警、灰度、回滚和客服处置方案就绪。

## 11. 审计边界与不能从前端确定的事项

无法从当前文件可靠恢复：数据库 schema 与历史数据、服务端必填规则、完整枚举、密码/密钥、微信 AppSecret、支付证书、退款与对账实现、定时任务、消息模板、短信服务、对象存储权限、报表口径、薪资口径、跨店结算规则、管理员操作审计、数据迁移历史。

因此任何“按前端字段直接造库即可上线”的方案都是高风险假设。尤其报表、余额、卡次数、退款和薪资必须取得业务负责人确认，并用历史账单/截图/线上导出反向校验口径。

## 12. 最终判定

代码资产的页面完整度较高，足以支持界面和接口契约逆向；但它不包含系统真正的权威状态和安全边界。当前不能上线，主要矛盾不是修几个前端报错，而是恢复身份、租户、课程、会员卡、预约、资金和报表的一致后端。

短期目标应定为“在隔离环境恢复可登录、可遍历、可抓取契约”；中期目标是“恢复核心业务闭环并完成账务校验”；长期目标是“迁移到有源码、有测试、有监控、可回滚的工程”。
