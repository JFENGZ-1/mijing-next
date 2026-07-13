# 会员端微信小程序全量审计报告

> 审计对象：`会员端/`（原生微信小程序发布产物，实际由 uni-app/Vue 2 编译）  
> 审计日期：2026-07-11  
> 原则：不修改业务源码；结合全文件自动清点、入口/网络层反向阅读、页面逐目录阅读和高风险能力抽样复核。  
> 结论强度：文件和静态引用是确定事实；后端字段语义来自调用方反推，标为“推断”的部分必须以真实流量或原后端数据库校准。

## 1. 执行结论

会员端不是可持续开发的源代码，而是一份已经打包、混淆和分块后的微信小程序发布产物。仓库中有 386 个文件（约 2.30 MB），35 个注册页面，页面四件套齐全；但缺失原始 `.vue`、TypeScript/JavaScript 源模块、`package.json`、构建配置、锁文件、环境文件、源映射、接口类型和测试。因此它可用于逆向规格和短期抢救，不应作为长期迭代基线。

当前不能直接复原上线，至少有以下 P0 阻断：

1. 后端整体遗失。前端依赖 49 个唯一 HTTP 接口、上传接口、微信支付下单、微信卡包签名、动态字典和大量远程静态资源。
2. `project.config.json` 没有 `appid`；需要找回原会员端小程序主体、AppID、支付商户绑定、服务类目及管理员权限。
3. 生产接口固定为 `https://interface.songguoyueke.com/api`，隐私协议固定为 `https://www.songguoyueke.com/propre.html`；必须确认域名、证书、备案、所有权和微信后台白名单。
4. 本地只有 8 个 PNG（6 个 tabBar 图标、`startlogo.png`、`laba.png`）。约 129 处业务图片引用依赖 `/common/dict` 返回的 `uploadURL` 和原对象存储目录，后端静态资源丢失会造成大面积空图。
5. 登录、手机号、支付、微信卡包和分享链路都依赖原后端签名/密钥。仅仿造 JSON 无法完成上线。
6. 请求协议把 `tokenId` 与 `deviceId` 放在 URL 查询串；新后端若不兼容则全部登录态接口失败，若照搬则有日志泄漏风险。

建议把现有产物视为“可执行需求文档”：先冻结并抓取仍可访问的线上接口/资源，再按本文契约重建后端，同时恢复一套可维护的 uni-app 源工程。

## 2. 审计覆盖与对抗式校验

### 2.1 覆盖范围

- 根入口：`app.js`、`app.json`、`app.wxss`、项目配置。
- 编译运行时：`common/runtime.js`、`common/vendor.js`、`common/main.js`。
- 主包：`pages/`，14 个页面。
- 分包：`pageHome/` 7 个页面，`pageCourse/` 4 个页面，`pageMine/` 10 个页面。
- 自研组件：`components/` 及各页面内嵌组件。
- 第三方：`node-modules/uview-ui/`、Babel helpers。
- 静态资源、导航、组件声明、WXML 事件、所有接口导出和调用点。

### 2.2 自动扫描不能直接相信的地方

- “MissingAssetReferences=0”只代表 `imgsrc()` 被识别成后端资源，不代表资源真实存在。仓库没有这些图片。
- “IncompletePages=0”只代表 `.js/.json/.wxml/.wxss` 齐全，不代表页面业务可执行。
- “MissingNavigationTargets=0”只覆盖可静态解析的跳转；分享参数、二维码 `scene`、动态回退和登录后分流仍需运行验证。
- 事件扫描只发现 2 个缺失处理器，但 uni-app 编译后的统一 `__e` 分发、组件 `$emit` 和动态方法可能造成误判；已手工复核两处确定缺失。
- API 参数只能从调用点反推。无调用导出、条件分支字段、后端默认参数、数据库约束和错误码无法由前端完整恢复。

## 3. 技术架构

### 3.1 技术栈与产物形态

- uni-app 编译到微信小程序，Vue 2 运行时。
- Vuex 风格全局 store，uView UI 组件库。
- webpack JSONP 分块，Babel runtime，代码已编译且变量名压缩。
- 无 npm 工程元数据，无法可靠重装依赖或重新构建。
- 主包约 1.700 MiB；分包：`pageCourse` 0.271 MiB、`pageHome` 0.095 MiB、`pageMine` 0.126 MiB。当前体积接近但未超过常见主包限制，仍应以微信开发者工具最终校验为准。

### 3.2 启动与全局状态

`common/main.js` 在 `onLaunch`：

1. 读取 `getSystemInfoSync()` 到 `systemInfo`。
2. 调用 `/common/dict` 获取默认图片和 `uploadURL`。
3. 清除 `skipDate`、`siteId` 临时存储。

Store 核心状态：

| 状态 | 用途 |
|---|---|
| `systemInfo` | 设备信息，请求层取 `deviceId` |
| `userInfo` | 登录响应整体，持久化到本地 `userInfo` |
| `commonData` | 默认卡图、课程图、员工/用户头像、商标、`uploadURL` |
| `appointmentData` | 约课页跨页面参数 |
| `mineInfo` | 资料编辑中间态 |
| `mineSelectedCard` | “我的”当前会员卡 |
| `hasWriteOperation/lastWriteTime` | 写后 2 秒内强制读主库，其他查询允许 `dsname=slave` |

### 3.3 网络协议

- 生产基址硬编码：`https://interface.songguoyueke.com/api`。
- 测试基址存在但编译常量固定 `production`：`https://test.songguoyueke.com/api` 实际不可切换。
- 全部业务接口使用 POST；大多为 `application/x-www-form-urlencoded`，3 个写接口为 JSON。
- 请求拦截器将原 URL 参数、`deviceId`、登录后的 `tokenId` 拼入 query string，并从 body 删除同名字段。
- 查询接口通常附加 `dsname=slave`；发生写请求后的 2 秒内不走从库。
- 响应拦截器直接返回 `response.data`，只对网络失败统一提示“网络不稳定”，没有全局业务错误、401/令牌过期、重试、超时或幂等处理。
- 响应拦截器在生产包中 `console.log(e.config)`，可能把 URL 中的 token 输出到调试日志。

## 4. 页面与业务地图

### 4.1 主包

| 页面 | 业务职责 | 主要依赖 |
|---|---|---|
| `pages/start/index` | 启动分流、二维码/分享参数解析、登录、按 `go` 跳转 | `scene`, `siteId`, `c`, `sid`, `go`, `/wxlogin` |
| `pages/index/index` | 首页、场馆信息、轮播、通知、近期预约、购卡/二维码入口 | 场馆、通知、预约、用户资料接口 |
| `pages/appointmentCourse/index` | 约课首页，私教/团课列表、日期切换、倒计时 | 私教列表、团课计划 |
| `pages/mine/index` | 我的资料、会员卡、积分、订单、统计、隐藏卡 | `myMainpage`、卡包、隐藏卡 |
| `pages/tailor/...` | 头像裁剪 | 选择图片、Canvas、预览、事件总线 |
| `pages/authorization/info/index` | 新用户头像昵称录入 | 隐私授权、头像上传、本地 `authorizationInfo` |
| `pages/authorization/phone/index` | 手机号授权、注册 | 手机号 code 解密、`register` |
| `pages/authorization/noLogin/index` | 场馆不可登录/访客提示 | URL 中 JSON `siteInfo` |
| `pages/receiveCard/.../info` | 分享领卡流程头像昵称 | 隐私、上传 |
| `pages/receiveCard/.../phone` | 分享领卡手机号、UnionID、领取卡 | 手机号、wx.login、UnionID、领取 |
| `pages/receiveCard/index` | 赠卡/领卡落地页 | `sharekey`、卡详情、分享 |
| `pages/not/index` | 未注册/不可用过渡页 | `authorizationInfo` |
| `pages/webView/index` | 固定外链协议页面 | `www.songguoyueke.com/propre.html` |
| `pages/myOrder/index` | 订单列表，支持登录态和 `jscode` 免 token 查询 | 订单接口、wx.login |

### 4.2 `pageHome` 分包

| 页面 | 业务职责 |
|---|---|
| `shopDetails/index` | 场馆地址/电话等详情、拨号 |
| `toggleShop/index` | 切换默认场馆并重新登录 |
| `informDetails/index` | 根据通知 ID 从通知列表中查详情 |
| `appointmentDetails/index` | 单条预约详情 |
| `buyingCard/index` | 可售会员卡列表、停业校验、续卡/购卡入口 |
| `buyingCard/buySuccess` | 支付成功落地 |
| `QRcode/QRcode` | 会员二维码视觉页；未发现动态生成二维码逻辑，核心内容很可能依赖后端图片/模板 |

### 4.3 `pageCourse` 分包

| 页面 | 业务职责 |
|---|---|
| `coachCourse/index` | 私教详情、时段选择、预约、取消、分享 |
| `coachCourse/share-index` | 无 token 私教分享落地，再引导登录/预约 |
| `clusterCourse/index` | 团课详情、预约/排队/取消、成员列表、分享 |
| `clusterCourse/share-index` | 无 token 团课分享落地 |

### 4.4 `pageMine` 分包

| 页面 | 业务职责 |
|---|---|
| `myInfo/index` | 资料总览和编辑：手机号、性别、生日、身份证、身高、体重、头像等 |
| `modifidInfo/index` | 单字段编辑，手机号需微信授权 |
| `myInterests/index` | 会员卡权益富文本 |
| `useRecord/index` | 约课、卡变更、余额变更三类记录 |
| `removeCard/index` | 已隐藏会员卡恢复 |
| `memberAgreement/index` | 会员协议 |
| `totalStatistics/index` | 年度约课统计 |
| `appointmentStatistics/index` | 月度明细、日历统计 |
| `rankingRecord/index` | 本月排行 |
| `point/index` | 积分总额和流水分页 |

## 5. 组件清单

自研公共组件：周历、隐私授权弹窗、通用对话框、预约列表、会员卡、全部项目卡、日期/次数/储值卡、提示面板、底部品牌、加载动画。业务内组件包括首页卡片/场馆选择、购卡确认与三类卡片、课程时间选择、会员卡选择、预约/排队成功弹窗、团课成员列表、私教确认弹窗、资料页自定义导航。

明显历史问题：`mumber-card`、`modifidInfo`、`confrimMoadl` 等拼写错误已经进入编译契约；重建源码时可以内部改名，但输出页面路径、事件名和后端字段要兼容旧分享链接。

## 6. 后端 API 完整目录

共同约定（前端现状）：所有 URL 自动增加 `deviceId`，除 `/wxlogin` 外登录后增加 `tokenId`；成功通常为 `code=200`，展示错误通常读 `msg`。`210`、`220`、`560` 是已知特殊码。以下“响应字段”均是调用方实际读取字段。

### 6.1 登录、微信能力、公共配置

| 接口 | 请求参数 | 响应字段/用途 |
|---|---|---|
| `POST /common/dict` JSON | 无 | `defaultCardImg`, `defaultCourseImg`, `defaultStaffFace`, `defaultTrademark`, `defaultUserFace`, `uploadURL` |
| `POST /c/user/wxlogin` form | `code`, 可选 `siteid/siteId` 等启动参数 | `code`, `tokenId`, `isVisitor`, `sitelist`, `clientConfig`, `siteInfo`；`560` 跳不可登录页 |
| `POST /wx/getWeixinPhoneNumber` form | `code`, `gztype=3` | `data.phone_info.purePhoneNumber` |
| `POST /wx/getUnionId` form | `code`, `gztype=3` | UnionID 相关返回，领卡注册继续使用 |
| `POST /c/user/register` form | `authorizationInfo` 中头像/昵称 + `userPhone` + 场馆/启动信息（组合对象） | 登录态用户对象，成功后写入 store；`code/msg` |
| `POST /common/uploadfile` multipart | 文件字段 `file` | `code`, `dbUrl`; 当前上传未附显式 token/header |

### 6.2 首页、场馆、通知、订单

| 接口 | 请求参数 | 响应字段/用途 |
|---|---|---|
| `/c/user/getSiteFaceimage` | 无 | `data.imglist`, `data.defImage` |
| `/c/user/getNoticeList` | 调用处多为空 | `datalist[]`，通知详情按 `noticeId` 前端查找 |
| `/c/user/myMainpage` | 通常空 | `user`, `cardlist`, `hellomsg`, `pointStarted` |
| `/c/user/getUserInfoForUpdate` | 空 | 以 `code` 判断是否允许购卡/资料完整 |
| `/c/user/myOrderList` | 空 | `list[]` |
| `/c/user/myOrderList_notoken` | `jscode` | `list[]`，免 token 订单查询 |
| `/c/user/putweixinList` | 空 | `cardlist[]`, `user`, `hellomsg`；筛选 `isPutWeixin=0` |
| `/c/user/getwxCardParam` | `{userCardId}` 等 `parameter` | `cardInfo.cardId/code/nonceStr/signature/timestamp/outer_str` |
| `/c/user/putweixincard` | 同上 | 保存加入微信卡包结果，`code/msg` |

### 6.3 约课和课程

| 接口 | 请求参数 | 响应字段/用途 |
|---|---|---|
| `/c/user/findAllPrivateDrainerList` | 前端调用对象/空 | `datalist[]` 私教列表 |
| `/c/user/findTeamPlan` | `oneday` | `list[]`, `mode`（场馆/营业状态） |
| `/c/user/findOneDrainerDetail` | `drainerId` | `list`, `data`, `msglist` |
| `/c/user/findOneDrainerDetail_noToken` | `drainerId`, `sign` | 同上，分享落地 |
| `/c/user/getDrainerTimeList` | `drainerId`, `begintime`, `pcourseId` | 可预约时段列表；调用方按上午/下午/晚上拆分 |
| `/c/user/getOnePlan` | `arrangeId` | `data`, `msglist`，含 `appointId/userAppointment/userQueue/queueIndex` 等 |
| `/c/user/getOnePlan_noToken` | `arrangeId`, `sign` | 同上，分享落地 |
| `/c/user/getwarmHint` | `coursetype`（私教 6、团课 7） | `data` 温馨提示 |
| `/c/user/getwarmHint_noToken` | `coursetype`, `dataid`, `sign` | `data` |
| `/c/user/getCardListForPay` | 课程/时段参数对象 | `cardlist[]`，每卡含可用项目与余额/次数等 |
| `/c/user/applyAppointment` JSON | `userCardId`、课程/时段参数、扣费数量等组合对象 | `code/msg`，成功弹窗 |
| `/c/user/replaceFormLine` JSON | `userCardId`、`arrangeId` 等排队对象 | `waitUserCount`, `waitUserIndex`, `code/msg` |
| `/c/user/cancelAppoint` | `appointid` | `code/msg`，用于取消预约和排队 |
| `/c/user/selectAppoint` | 首页预约查询对象（含日期/分页语义） | `list[]` |
| `/c/user/selectOneAppoint` | `appointId` | `data` |

### 6.4 会员卡、购卡、协议

| 接口 | 请求参数 | 响应字段/用途 |
|---|---|---|
| `/c/user/checkCloseSite` | 空/场馆隐式 token | `exists`, `code/msg`；前端随后强制写成 `false`，见缺陷 |
| `/c/user/getAllCardInfo` | 空 | `cardlist[]`，卡型/价格/默认卡/续卡信息 |
| `/c/user/submitcard` | `cardId` 或 `userCardId` | `data.timeStamp/nonceStr/packageStr/signType/paySign`; `210/220` 特殊分支 |
| `/c/user/cardPrivilege` | `cardId` | `data` 富文本权益 |
| `/c/user/getuserProtocolSetting` | 调用处可能空 | `data` 富文本会员协议 |
| `/c/user/getUserCardInfo` | `sharekey` | `cardlist`, `validmsg`，卡内含 `userId/userCardId/cardId` 等 |
| `/c/user/takeByuserCardId` | `sharekey`, `userCardId/cardId`, phone/union 信息组合对象 | `code/msg`，领取卡并返回/建立登录态 |
| `/c/user/deleteUserCard` | `userCardId` | 隐藏卡，`code/msg` |
| `/c/user/finddelUsercard` | 空 | `cardlist[]` |
| `/c/user/recoverdelUserCard` | `userCardId` | `code/msg` |

### 6.5 资料、记录、统计、积分

| 接口 | 请求参数 | 响应字段/用途 |
|---|---|---|
| `/c/user/getMyUserInfo` | 空 | `userField[]` 及用户资料；字段含 `userPhone/userSex/userBirthday/userIdent/userHeight/userWeight/userFaceurl` |
| `/c/user/UpdateUserInfo` JSON | 上述资料表单 | `code/msg` |
| `/c/user/findUserAppointList` | `pagesize`, `pageno`, `userCardId` | 分页预约使用记录列表 |
| `/c/user/findModifyLog` | 同上 | 会员卡修改记录列表 |
| `/c/user/findAmountChangeLog` | 同上 | 余额变动记录列表 |
| `/c/user/sumUserList` | 前端调用对象/空 | `yearlist`, `totalCount` |
| `/c/user/selectAppointOfMonth` | 月份参数对象（含分页） | 月预约明细列表 |
| `/c/user/sumAppointOfMonth` | `month`, `year` | `data` 月统计 |
| `/c/user/rankList` | 调用对象/空 | `list[]`, `myRank.userRealname/userFaceurl/indexnum/ncount` |
| `/c/user/PointListByUserId` | `pageno`, `pagesize` | `userInfo.descText/totalPoint`, 积分流水列表 |

### 6.6 已导出但使用证据较弱的接口

| 接口 | 说明 |
|---|---|
| `/c/user/getAllCardInfo`, `/c/user/getCardListForPay` | 已有明确调用，字段仍需抓包补全卡型差异 |
| `/c/user/putweixinList`, `/c/user/rankList` | API 元数据把 `rankList` 标成写操作，语义上应是查询，疑似旧代码误标 |
| `/c/user/getuserProtocolSetting` | 在两个打包模块重复导出同一路径，不是两个后端接口 |

## 7. 登录与授权流程

### 7.1 正常启动

`pages/start/index` 解析普通 query 或二维码 `scene`。`scene` 预期是 URL query 形式，字段包括 `siteId`, `c`, `go`, `sid`。随后调用 store `getLoginInfo`：

- 有 `__wxConfig.envVersion` 且为 `develop` 时，前端把 code 写成字面量 `test`。
- 体验版/正式版使用 `wx.login` 返回的真实 code。
- 没有 `__wxConfig` 时也使用 `test`。
- 成功 `200`：整个响应作为 `userInfo`，写 store 和本地存储。
- `560`：携带 URL 编码后的 `siteInfo` 跳转 `authorization/noLogin`。
- 其他码：Promise reject，但多数页面没有用户可恢复的统一处理。

`go` 控制分享后的目标：首页、私教、团课、约课、“我的”、购卡等；旧分享链接是必须兼容的外部契约。

### 7.2 新用户注册

1. 隐私组件调用 `wx.getPrivacySetting`，需要时展示协议。
2. 用户用 `open-type=chooseAvatar` 选择头像，上传 `/common/uploadfile`，把 `dbUrl` 保存到 `authorizationInfo`。
3. 手机号页通过 `open-type=getPhoneNumber` 获取一次性 code，后端 `/wx/getWeixinPhoneNumber` 解密。
4. `register` 组合本地头像昵称、手机号、场馆/分享参数完成注册，响应直接成为登录态。

### 7.3 领卡注册

领卡链路额外使用 `sharekey`、`wx.login`、`getUnionId`、`takeByuserCardId`。这是高风险资产转移流程，后端必须做到：sharekey 不可预测、一次性/有时效、绑定卡和发送方、幂等、防重放、领取人校验、完整审计日志。

## 8. 支付、分享、二维码、上传、卡包

### 8.1 微信支付

购卡确认组件调用 `/c/user/submitcard`，后端返回五项小程序支付参数后执行 `wx.requestPayment`。前端支付成功即跳成功页；没有订单二次查询、支付结果轮询或前端幂等证据。后端必须以微信支付回调为最终真相，并提供订单查询/补单机制，不能信任前端 success。

### 8.2 分享

- 全局默认分享：`/pages/start/index?siteId=...`。
- 约课页携带 `go=5`，我的携带 `go=6`，购卡携带 `go=7`。
- 私教/团课详情分享携带 `drainerId/arrangeId`, `sign`, `siteId`，落到无 token `share-index`。
- 领卡分享携带 `sharekey`。
- 多处实现 `onShareAppMessage`，团课还有 `onShareTimeline`。

分享签名 `sign` 和 `sharekey` 都必须由后端签发并防篡改；无 token 接口不能只靠可枚举 ID 返回会员或课程敏感数据。

### 8.3 二维码

- 启动页支持微信二维码 `scene` 参数。
- `pageHome/QRcode` 只看到静态远程素材引用，未看到本地二维码编码、Canvas 绘制或获取动态二维码接口。
- 因此“会员二维码”功能的真实载荷可能嵌在后端图片、WXML 数据或已遗失逻辑中，必须用原线上版本实测确认，不能仅凭页面名实现。

### 8.4 头像上传

三处上传到 `/common/uploadfile`，文件字段名 `file`，返回 `dbUrl`。上传调用没有复用请求拦截器，未显式携带 `tokenId/deviceId`，且在 `complete` 回调里无条件 `JSON.parse(response.data)`：网络错误、非 JSON 或空响应会抛异常；错误分支还错误读取 `t.msg` 而非解析后的 `n.msg`。后端需限制 MIME、扩展名、文件大小、图片解码、恶意内容和对象存储权限。

### 8.5 微信卡包

`getwxCardParam -> wx.addCard -> putweixincard`。复原需要原公众号/小程序与微信卡券权限、cardId、签名密钥和卡券模板；仅恢复 API 返回结构无法成功。

## 9. 静态资源与域名

### 9.1 本地资源

实际业务本地文件只有：

- `static/tabbar/`：首页、约课、我的各普通/选中图标，共 6 张。
- `static/imgs/startlogo.png`、`static/imgs/laba.png`。
- 7 个无业务价值的 `.DS_Store` 元数据文件。

### 9.2 动态资源

`imgsrc()` 会把 `/static/imgs/foo.png` 去掉 `/static`，再拼成 `commonData.uploadURL + /imgs/foo.png`。因此诸如默认头像、按钮、课程角标、成功图、排名背景、品牌 logo、卡状态、首页入口、领卡背景等约 129 处资源全部依赖原远程目录。

特别注意存在路径风格不一致：`/static/imgs/...`、`@/static/imgs/...`、`imgs/202501/...`、`imgs/202505/...`、`imgs/202510/...`。`imgsrc()` 只特殊处理包含 `/static` 的字符串，`@/static/...` 会变成 `@/imgs/...`，很可能是实际坏图或依赖服务端奇怪目录，必须逐张校验。

### 9.3 必须配置的微信后台域名

- request/uploadFile：`https://interface.songguoyueke.com`（或新 API 域名）。
- web-view 业务域名：`https://www.songguoyueke.com`。
- downloadFile/图片域名：由 `/common/dict.uploadURL` 决定，必须纳入 downloadFile 合法域名并保持 HTTPS。
- 测试域名：`https://test.songguoyueke.com`（若恢复环境切换）。

## 10. 明显缺陷与安全问题

### P0：上线阻断

1. **后端、数据库、对象存储、支付/微信密钥全部缺失。** 49 个接口及资源目录无法由前端自动还原真实数据和业务规则。
2. **缺少 AppID。** `project.config.json` 只有构建设置，没有小程序身份配置。
3. **不是源工程。** 无法可靠升级基础库、依赖、隐私 API、手机号 API 或重新构建。
4. **远程资源是隐式硬依赖。** `/common/dict` 失败时 `commonData` 为空，`imgsrc` 返回原 `/static/...`，但绝大多数对应本地文件不存在。
5. **支付/卡包需要原主体权限。** 新 AppID 或新商户号不能直接沿用旧签名和 cardId。

### P1：高风险缺陷

1. **token 暴露在 URL。** `tokenId`、`deviceId` 会进入网关日志、CDN/代理日志、APM、错误日志和控制台；应迁移到 Authorization/header，并在过渡期双协议兼容。
2. **开发登录逻辑可疑。** develop/无 `__wxConfig` 使用字面量 `test`；若后端保留测试后门，可能形成认证绕过，生产后端必须拒绝该值。
3. **生产环境不可配置。** 编译常量固定 production，开发者工具也直连生产接口，容易污染真实数据。
4. **无 token 分享接口风险。** `*_noToken` 仅见 ID + `sign`；必须验证签名覆盖字段、有效期、用户/场馆边界和防重放。
5. **领卡资产转移风险。** 需要一次性、幂等和并发锁，否则同一卡可能重复领取或越权领取。
6. **支付状态仅靠前端跳转。** 前端 success 不是到账凭证；必须以服务端支付通知和订单状态为准。
7. **个人敏感信息。** 前端处理手机号、身份证、生日、身高、体重、头像；需最小化收集、明确隐私声明、加密存储、访问控制、删除/注销机制和日志脱敏。
8. **上传无健壮错误处理。** `complete` 中直接 JSON.parse，失败也可能崩溃；未见客户端大小/类型限制。
9. **富文本信任后端。** 协议和权益通过 rich-text/u-parse 渲染；后端必须清洗 HTML、限制链接协议和外部小程序跳转。

### P2：确定或高度可信的业务 Bug

1. `pageCourse/clusterCourse/share-index.wxml:112` 监听成功组件 `ok -> close`，页面 JS 没有 `close` 方法；取消成功弹窗点击确定可能无响应/报错。
2. `pages/receiveCard/index.wxml:20` 的会员卡组件监听 `moreProject`，页面 JS 没有该方法；点击更多项目无响应。
3. `pageHome/buyingCard/index.js` 在 `checkCloseSite` 成功后先把后端响应赋给 `exists`，随即执行 `r.exists = false`，等于无条件关闭停业拦截。此处很可能导致停业场馆仍可发起购卡。
4. 上传错误提示读取外层响应对象 `t.msg`，而成功结构解析为 `n`；错误信息大概率显示为空。
5. `rankList` 被标记为写操作，会触发 2 秒主库粘滞；虽不破坏结果，但说明 API 元数据/复制代码不可信。
6. `onShareTimeline` 返回了 `path`；朋友圈分享 API 通常关注 `query` 而非 `path`，需用当前基础库实测。
7. `getSystemInfoSync().deviceId` 在新基础库/隐私策略下可能为空或受限；后端不能把它当稳定设备身份。
8. `informDetails` 重新拉全部通知后按 ID `find`，若通知被删除、分页或接口失败，页面没有明确空态防护。
9. 多处 API 成功判断不一致，有的直接读取 `datalist/list` 不检查 `code`，后端异常结构会触发空对象错误。
10. 大量 `setTimeout` 驱动登录后跳转，缺少取消和页面卸载保护，慢网/重复进入可能造成重复导航。

### P3：工程质量

- 生产包残留 `console.log`，包括请求 config、启动参数、领卡响应。
- 没有统一 token 失效处理、埋点、错误边界、请求取消、超时策略或版本更新管理。
- 无测试、lint、类型、CI/CD、环境隔离和接口 schema。
- 7 个 `.DS_Store` 应清理；第三方源码直接打入仓库，许可证和版本不明确。
- 多处空 `fail/complete`、空方法 `shareCourse`，可观测性差。

## 11. 后端复原最小模型

从前端可推断的核心实体：

| 实体 | 关键字段/关系 |
|---|---|
| 用户 User | userId, tokenId, phone, union/open id, realname/nickname, faceurl, sex, birthday, ident, height, weight |
| 场馆 Site | siteId, siteName, phone/address, default flag, status/mode, images, clientConfig |
| 教练 Drainer | drainerId, 头像、性别、介绍、私教课程、时段 |
| 课程/排课 | courseId/pcourseId, arrangeId, coursetype(6/7), 日期时间、容量、状态、价格/扣次 |
| 预约 Appointment | appointId, userId, userCardId, arrangeId, 状态、排队序号、扣费与退款 |
| 卡模板 Card | cardId, cardType（储值/次数/期限等）、价格、有效期、权益、可用项目 |
| 用户卡 UserCard | userCardId, userId, cardId, 余额/次数/有效期、隐藏状态、微信卡包状态 |
| 订单 Order | orderId, user/card/site, amount, 微信支付单号、状态、回调、幂等键 |
| 通知 Notice | noticeId, 标题、正文、时间、场馆 |
| 积分 PointLog | userId, change, totalPoint, descText, createdAt |
| 分享 ShareToken | sign/sharekey, 类型、目标 ID、发送者、过期时间、使用状态 |

必须补齐数据库唯一约束和事务：预约容量、排队换位、扣次/余额、取消退款、购卡订单回调、领卡所有权转移均不能只靠应用层先查后写。

## 12. 上线恢复路线

### 阶段 A：证据保全（最优先）

1. 不要先改域名或覆盖产物；对当前目录做只读快照和哈希。
2. 确认旧小程序 AppID、微信公众平台账号、原主体、管理员、微信支付商户号、API v3 key/证书、卡券权限。
3. 确认三个域名和 DNS/证书控制权；若线上接口仍活着，立即在授权测试账号下抓取完整请求/响应和错误码。
4. 调用 `/common/dict` 保存 `uploadURL`，镜像对象存储的 `/imgs/` 全目录，逐项核对本文资源路径。
5. 导出微信后台隐私保护指引、服务器域名、业务域名、类目、订阅消息（本代码未发现订阅调用）、版本记录。

### 阶段 B：契约化后端

1. 根据 49 个接口建立 OpenAPI，保留旧路径/字段/错误码作为兼容层。
2. 优先实现 `/common/dict`、`/wxlogin`、手机号、首页/场馆，再做约课，再做会员卡与支付。
3. 为 token 建立 header 新协议，同时短期兼容 query token；网关和应用日志必须脱敏。
4. 对无 token 分享接口、领卡和支付做威胁建模、幂等、过期、签名和审计。
5. 建立主从读一致性替代方案；旧前端会发送 `dsname=slave`，后端必须决定兼容或忽略。

### 阶段 C：重建可维护前端

1. 新建 Vue 3 + TypeScript 的 uni-app 源工程，保持 35 个路由和分享 query 兼容。
2. 从页面业务而非反编译代码逐页迁移；先登录/首页/约课/我的，再支付/领卡/卡包。
3. 把资源纳入版本化 CDN 清单，禁止运行时靠隐含目录猜路径。
4. 建立 API 类型、错误码、环境配置、自动化测试、隐私与支付测试矩阵。
5. 修复本文 P1/P2 后再提交审核，不建议直接在压缩产物上长期打补丁。

### 阶段 D：验收矩阵

- 身份：新用户、老用户、访客、不同场馆、token 过期、拒绝隐私、拒绝手机号。
- 约课：私教/团课、满员排队、取消、退款、重复点击、并发最后一个名额、分享落地。
- 卡：储值/次数/期限卡、续卡、隐藏/恢复、权益、微信卡包。
- 支付：成功、取消、超时、重复回调、支付成功前端掉线、退款。
- 领卡：过期、已领取、自己领取、多端并发、篡改 sharekey。
- 平台：真机 iOS/Android、开发/体验/正式版、弱网、冷启动、二维码 scene、朋友圈分享。

## 13. 仍需外部证据确认的问题

1. 原 AppID、商户号、公众号/开放平台绑定关系是什么？
2. `interface.songguoyueke.com` 和对象存储是否仍可访问，数据库是否存在备份？
3. `/common/dict.uploadURL` 的真实值及完整资源目录是什么？
4. `code=210/220/560` 和其他错误码的精确定义是什么？
5. 各卡型、扣费、排队、取消退款、停业策略的业务规则是什么？
6. `QRcode` 页面展示的是用户身份码、卡码还是场馆码，验码端在哪里？
7. `test` 登录是否是后端测试后门？若是，必须在生产彻底禁用。
8. 旧分享 `sign/sharekey` 的算法、密钥、过期策略能否恢复？
9. 隐私政策是否覆盖身份证、健康相关体征、头像、手机号和设备信息的实际处理？

## 14. 最终判断

前端页面结构保存得相对完整，足以复原产品外观、路由和大部分接口契约；真正决定能否上线的不是继续修补这份编译代码，而是找回微信主体资产、线上接口/数据库/对象存储证据，并重建安全且可维护的后端与源工程。若线上域名仍存活，证据保全的价值远高于任何先行重写。
