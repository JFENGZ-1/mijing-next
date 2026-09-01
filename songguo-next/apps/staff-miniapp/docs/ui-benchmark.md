# 觅境工作台 UI 对标规范（执行 AI 用）

> 本文件是对标目标（管理端，旧 uni-app Vue2 + uView1.x 编译产物）提炼出的视觉与结构规范。
> 执行 AI 只需读本文件即可还原 UI，**不需要也无法访问对标源文件夹**。
> 当前项目技术栈：uni-app Vue3 + TypeScript + uview-plus + Pinia。
> 原则：**只换皮（模板 + 样式），不动逻辑**（权限 `session.can()`、`src/api/*`、`src/types/*` 全部保留）。

---

## 1. 对标目标的本质

管理端是"消费级 App"视觉，不是管理后台风格。核心特征：
- 品牌主色为**金黄色 `#fbd128`**（顶部栏、固定栏、按钮主色），不是蓝色。
- 营业额/累计收款等核心数字用**橙色 `#ed920f`** 大号展示。
- 页面背景统一 `#f5f5f5`，内容卡片为纯白 `#fff`，卡片顶部圆角 `20rpx 20rpx 0 0` 向上压在彩色顶栏上。
- 顶部固定彩色区（金黄/带背景图）+ 白色圆角内容区"上压"布局是全站统一范式。
- 单位一律 `rpx`，沿用 uView 工具类（`u-flex`/`u-row-between`/`u-line`/`u-gap`/`u-divider`/`u-tag`/`u-button`/`u-modal`/`u-popup`）。

---

## 2. 设计令牌（Design Tokens）

### 2.1 颜色

| 用途 | 值 | 说明 |
|---|---|---|
| 品牌主色 brand | `#fbd128` | 顶部栏、固定栏、主按钮、筛选区背景 |
| 主按钮 hover/active | `#f0c000` | `com-button-f1` hover 态 |
| 核心数字橙 revenue | `#ed920f` | 营业额、累计收款、income_num |
| 续费/提醒橙 warn | `#ee8231` / bg `#ffeae1` | 续费标签、提醒类 |
| 提示橙弱 | `#e98900` | `com-button-f2` 文字、icon-add |
| 新会员绿 success | `#5fc48d` / `#5bc888` / bg `#ecf8f3` | 新会员标签、选中态、团课标签 |
| 绿强调 | `#22c788` | flagActive 边框、active 标签 |
| 正文 text | `#181818` | 主要文字 |
| 次要灰 secondary | `#989898` | 标签、副标题、说明文字 |
| 输入文字 input | `#7e7e7e` | input 颜色 |
| 占位符 placeholder | `#dadada` | placeholder-class |
| 分割线 border | `#f0f0f0` / `#e5e5e5` / `#dadada` | 表单行底线、三角形、边框 |
| 页面背景 page | `#f5f5f5` | `page { background }` |
| 卡面 surface | `#ffffff` | 卡片、内容区 |
| 危险红 danger | `#dc3c5c` / `#f97272` / `#e77a76` | 提醒条、续费按钮、公众号引导 |
| 报表深蓝 | `#223f60` / `#293455` / `#3d505f` | 报表副信息、说明文字 |
| 报表圆图标蓝/橙 | `#5fa3ea` / `#f19469` | 报表提醒区圆背景 |
| 通用卡遮罩 | `hsla(0,0%,9%,0.38)` | 会员卡面上的时间/课程标签底色 |
| 灰背景弱 | `#f8f8f8` / `#fafafa` / `#faf7f5` / `#f3f7fb` | 筛选标签未选、提醒卡片底 |
| 禁用 disable | `#787aa3` 文字 `#9495b5` | 初始设置禁用按钮 |

### 2.2 字号

| 用途 | 值 |
|---|---|
| 超大数字（营业额首页） | `90rpx` |
| 报表累计收款 | `86rpx` |
| 问候语/场馆名大标题 | `53rpx`（首页问候）/ `49rpx`（场馆名） |
| 导航标题 | `16px`（≈32rpx）粗体 700 |
| 区块标题（今日售卡/约课/团课） | `35rpx` 粗体 500 |
| 列表主信息 | `28rpx` |
| 列表副信息/标签 | `24rpx` |
| 极小说明 | `22rpx` |
| 空状态文字 | `25rpx` 灰 `#bfbfbf` |
| 默认基准 | `28rpx`（page 默认） |

### 2.3 圆角

| 用途 | 值 |
|---|---|
| 内容区顶部圆角（压在顶栏上） | `20rpx 20rpx 0 0` |
| 卡片/会员卡 | `18rpx` |
| 主按钮/输入框胶囊 | `100rpx`（圆角胶囊） |
| 搜索输入框 | `35rpx` |
| 弹窗 popup | `20rpx`（borderRadius） |
| 标签小圆角 | `19rpx` / `14rpx` |
| 模态按钮 | `17px` / `41rpx` |
| 头像/图标 | `50%`（圆形） |

### 2.4 间距（常用值）

- 页面横向内边距：`28rpx` ~ `35rpx`
- 卡片内边距：`28rpx` ~ `50rpx`
- 区块间距：`u-gap height=24`（即 24 高的灰色间隔条 `#f5f5f5`）
- 列表项上下内边距：`30rpx`
- 顶部固定区高度（首页 banner）：`333rpx`
- 底部固定按钮区高度：`170rpx`，按钮 `83rpx` 高、`458rpx` 宽

### 2.5 阴影

- 会员卡阴影：`box-shadow: 0 -2rpx 5rpx rgba(0,0,0,0.1)`
- 其余卡片基本无阴影，靠白底 + 灰背景区分层次。


---

## 3. 全局通用模式

### 3.1 页面基底
```scss
page { background: #f5f5f5; font-size: 28rpx; }
::webkit-scrollbar { display: none; }        // 隐藏滚动条
input { color: #7e7e7e; }
.placeholder-class { color: #dadada; }
.form-line-b { border-bottom: 1rpx solid #f0f0f0; }   // 表单行分隔
.visibility { visibility: hidden; }
```

### 3.2 顶部彩色固定栏范式（全站统一）
- 顶部固定区背景 `#fbd128`（或带背景图，首页 banner 用 `background:url(...)` + `background-size:100% 100%`，高 `333rpx`，`position:fixed;top:0`）。
- 状态栏占位：用 `uni.getSystemInfoSync()` 的 `StatusBar`（状态栏高）+ `CustomBar`（导航栏高）做占位，确保内容不被彩色栏遮挡。在 Vue3 里用 `uni.getWindowInfo().statusBarHeight` + 自定义导航栏高度。
- 内容区紧跟其后，`background:#fff; border-radius:20rpx 20rpx 0 0; margin-top:-20rpx;` 向上压住顶栏底部，形成"圆角卡片托起"效果。

### 3.3 自定义导航栏（custom-navigation）
对标 `components/navigation`：
- `position:fixed; top:0; width:100%; z-index:199`，背景随页面顶栏色（通常 `#fbd128`，报表页透明/白）。
- 标题居中：`font-size:16px; font-weight:700`。
- 返回按钮在左侧绝对定位：`width:75rpx; height:100%`，内含返回箭头图 `17rpx×29rpx`，点击 `uni.navigateBack()`。
- Vue3 实现：封装为 `<CustomNav :text="title" :bg="bgColor" />`，用 `uni.getWindowInfo().statusBarHeight` 撑高。

### 3.4 底部固定按钮区
```scss
.com-button-f1 {
  position: fixed; bottom: 0; width: 100%; height: 170rpx;
  background: #fff; display: flex; justify-content: center; padding-top: 25rpx; z-index: 99;
}
.com-button-f1 button {
  background: #ffcf00; border-radius: 100rpx; color: #181818;
  height: 83rpx; width: 458rpx; border: 0;
}
```
- 注意主按钮是**金黄胶囊** `#ffcf00`（比顶栏 `#fbd128` 略深），不是蓝色。
- 次级按钮（取消）：`background:#fff; border:1px solid #fbd128; color:#7e7e7e; border-radius:41rpx; height:83rpx; width:250rpx`。

### 3.5 输入框（搜索/表单）
```scss
.com-input { background: #f5f5f5; border-radius: 100rpx; height: 69rpx; padding: 0 20rpx; }
```
搜索框（会员页）：白底胶囊 `background:#fff; border-radius:35rpx; height:74rpx`，左侧放大镜图 `36rpx`，placeholder `#989898 26rpx`，右侧筛选按钮图标 `44rpx`。

### 3.6 模态弹窗（confirm-modal）
基于 `u-modal`，宽 `700rpx`，无默认标题/确认按钮（`showTitle=false showConfirmButton=false`），自绘：
```scss
.modal-title { font-size: 35rpx; font-weight: 600; padding: 48rpx 26rpx 0; text-align: left; }
.modal-content { color: #7e7e7e; font-size: 24rpx; line-height: 47rpx; padding: 33rpx 26rpx 0 44rpx; }
// 按钮区
.modal-cal-btn { background:#fff; border:1px solid #fbd128; color:#7e7e7e; }
.modal-btn     { background:#fbd128; border:1px solid #fbd128; }
// 通用：border-radius:41rpx; color:#181818; font-size:32rpx; height:83rpx; width:250rpx
```

### 3.7 底部弹层（ff-popup）
基于 `u-popup mode=bottom borderRadius=20 closeable`，内含可选返回箭头 `u-icon name=arrow-left`、标题、tips 提示行、`scroll-view` 主体、可选底部确认按钮槽。

### 3.8 空状态与底部 logo
- `nodata` 组件：图 `171rpx×203rpx` + 文字 `#bfbfbf 25rpx`，居中列排，高度约 `665~820rpx`。
- `ff-bottom-logo`：底部留白区高 `285rpx`，logo 图 `182rpx×67rpx`，版本号 `#a8a8a8 18rpx`。
- 列表底部"没有更多了"：`u-divider bgColor=#F5F5F5 borderColor=#dadada color=#bfbfbf halfWidth=74`。

---

## 4. 页面映射表（对标 → 当前项目）

| 对标页 | 当前项目文件 | 要点 |
|---|---|---|
| `pages/home/home` | `src/pages/index/index.vue` | 工作台首页，差异最大，优先做 |
| `pages/course/course` | `src/pages/course/index.vue` | 课程日程，教练头像横滑 + 团课/私教卡 |
| `pages/member/member` | `src/pages/members/index.vue` | 会员管理，金黄搜索栏 + 卡片网格 |
| `pages/report/report` | `src/pages/report/index.vue` | 报表中心，橙色大数字 + 柱状图 + 提醒卡片 |
| `pages/shop/shop` | `src/pages/settings/hub/index.vue` + 子页 | 场馆设置入口，金黄顶栏 + 功能模块宫格 |
| `pages/login/login` | `src/pages/login/index.vue` | 微信授权登录 |
| `pageConfig/*` | `src/pages/settings/*` 各子页 | 配置类页：预约、会员、支付、提醒等 |
| `pageReport/*` | `src/pages/report/*` 子页 | 各报表明细 |
| `pageMember/*` | `src/pages/members/*` 子页 | 会员详情、卡详情、筛选 |
| `pageChain/*` | `src/pages/settings/chain/*` | 连锁相关 |
| `pageImp/*` | `src/pages/settings/*` + `card-products` | 卡种、员工、授权 |
| `pageServer/*` | `src/pages/settings/support/*` | 服务协议、帮助 |


---

## 5. 逐页布局规范

### 5.1 首页（工作台） `pages/index/index.vue`

**结构（自上而下）：**
1. **固定 banner 区**（`fixed-box-wrap`，高 `333rpx`，`position:fixed`）
   - 全宽背景图（渐变暖色 banner，白字）。
   - 状态栏占位 `StatusBar+CustomBar`。
   - 左侧问候语：`time-greete` `53rpx/700` 白字（如"下午好"）+ 下一行 `greet-hint` `22rpx` 白字（提示语）。
   - 右侧场馆切换胶囊 `capsule-wrap`：切换图标 `38×42rpx` + 场馆名 `28rpx` 白字；仅多场馆时显示（`hasSiteCount>1`）。
2. **今日数据白卡**（`today-data`，`background:#fff; border-radius:20rpx 20rpx 0 0; margin-top:-20rpx; padding:75rpx 0 56rpx`，上压 banner）
   - 营业额大数字 `money`：`#ed920f 90rpx`，隐藏时显示 `******`。
   - 标签行 `money-text`：「今日营业额(元)」`#989898 25rpx` + 眼睛图标 `40×33rpx` 切换显隐。
   - 四指标横排 `data-box`：`flex; justify-content:space-around; padding:0 50rpx`，每项数字 `#181818 33rpx` + 标签 `#989898 22rpx`，项间用 `u-line color=#DDDDDD direction=col length=51rpx` 竖分隔。
     - 指标：预约团课(人)、预约私教(人)、售卡(张)、新增会员(名)。
3. **间隔条**：`u-gap bgColor=#f5f5f5 height=24`。
4. **今日售卡区**（`today-sale-box`）
   - 标题行 `title-label`：「今日售卡」`35rpx/500` + 笔数 `22rpx`。
   - 列表项 `sale-item`：左头像 `85×85rpx` 圆形 + 详情：会员名 `#181818 28rpx`、时间+类型行 `#989898 24rpx`（含新会员绿标 `#5fc48d`/bg`#ecf8f3` 或续费橙标 `#ee8231`/bg`#ffeae1`，标签 `18rpx`）、卡名 `#989898 24rpx`；右侧金额 `#181818 28rpx/500` + 支付方式 `#989898 24rpx`；底部 `u-line color=#ececed`。
   - 备注行：`remarkfont #989898` + `remarkcontent #c96a2f`。
   - 空态：`~ 没有会员购卡哦 ~`。
5. **今日约课区**（`appoint-box`，结构同上）
   - 标题「今日约课」+ 人次；每项含会员、时间、课程、教练；空态图 + 「还没有会员约课哦」。
6. **底部** `u-divider`「没有更多了哦」+ `ff-bottom-logo`。
7. **公众号引导条**（可选）：`#e77a76` 圆角条，白字 + 「去关注」白底红字按钮。

**当前 `index.vue` 需改动的点**：把现有"表单式网格 + 蓝色 action-button"换成上面的"banner + 大号橙营业额 + 横排四指标 + 卡片列表"；保留 `summary/salesFeed/appointmentFeed` 数据与 `session.can()` 权限判断、`loadDashboard()`/`onShow`/`onPullDownRefresh` 逻辑。

### 5.2 课程页 `pages/course/index.vue`

**结构：**
1. 顶部内容区 `main-content`：`background:#fff; border-radius:20rpx 20rpx 0 0`。
2. **私教区** `pt`：标题 `pt-font` `34rpx/500`「私教」+ 横向 `scroll-view` 教练头像列表。
   - 每项 `pt-scroll-item` 宽 `132rpx`：头像 `132×132rpx` 圆角 `12rpx` + 角标（排课数 `#fc8c00` 白字 tag）+ 教练名 `28rpx` + 已约头像堆叠（圆形 `35rpx`，`margin-left:-15rpx`，`border:1rpx solid #fff`）+ 人数 `14rpx #989898`。
3. **团课区** `group`：标题 + 横滑日期/课程。
4. **课程卡列表** `courseli`：
   - 卡片为彩色渐变背景（团课/私教不同色），白字。
   - 左模块：课程名、教练、会员头像堆叠 + 已约/满员标（满员 `#d95872` 圆形图标）。
   - 右模块：开始时间 `39rpx` 白字 + 结束时间 `21rpx` + 操作按钮。
5. 空态 `noCourseData`：图 `171×203rpx` + 「还没有会员约课哦」`#bfbfbf 25rpx`。

### 5.3 会员页 `pages/members/index.vue`

**结构：**
1. **金黄顶部搜索栏**（`fixed-box` `background:#fbd128; position:fixed; top:0`）
   - 搜索框白底胶囊 `74rpx` + 右侧筛选按钮（图标 `44rpx` + 文字 `21rpx`）。
   - 第二行更多筛选标签 `more-filter-box`：`background:rgba(0,0,0,0.1); border-radius:30rpx; height:70rpx`。
2. 内容区 `background_top` `margin-top:-18rpx` 上压。
3. **会员卡网格** `cardList`：每行 2 张，`width:50%`，卡图 `299×178rpx`，含余额角标、状态图、选中圆点 `#5bc888`。
4. 筛选弹层：标签 `item_flag` 未选 `#f8f8f8`、选中 `#ecf8f3` + `1rpx solid #22c788` + `#22c788` 文字。
5. 模态筛选确认：取消/确定按钮 `83rpx×250rpx`，确定 `#fbd128`、取消白底 `#fbd128` 描边。

### 5.4 报表页 `pages/report/index.vue`

**结构：**
1. `custom-navigation` 标题「统计报表」。
2. **顶部数据区** `top_data`（`padding:0 28rpx 71rpx`）：
   - 累计收款 `income_num`：`#ed920f 86rpx/500` 居中 + 「累计收款(元)」`#989898 25rpx`。
   - 柱状图 `columnChart` 高 `385rpx` 宽 `680rpx`（用 canvas 或 ucharts 实现）。
   - 更新时间行：`#223f60 18rpx` + 刷新图标 `20rpx`，右对齐。
   - 本月收款 `month_text` `39rpx/500` + 金额 `value 42rpx/500` + 「往月收款」按钮（箭头）。
   - 更多指标 `more-data`：本月售卡(张)、本月新增会员(名)，值 `42rpx/500` + 单位 `22rpx`。
3. **提醒区** `remind`：标题 `39rpx/500` + 圆形图标卡（`#5fa3ea`/`#f19469` 圆 `90rpx`）+ 提醒项卡（`#faf7f5`/`#f3f7fb` 底，`22rpx` 圆角，`#d16700` 文字）。
4. 说明弹窗（confirm-modal）+ 「立即重算」按钮。

### 5.5 场馆设置入口 `pages/settings/hub/index.vue`

**结构：**
1. **金黄顶栏** `fixed-bar` `background:#fbd128`：场馆 logo 圆形 `58rpx` + 场馆名 `36rpx/500`。
2. `venue-info-wrap` `#fbd128`：大场馆信息（图标 `126rpx` + 名称 `49rpx/500` + 地址 `22rpx` + 会员头像堆叠）。
3. 功能模块宫格 `module-group`：圆形图标底 + 文字 `22rpx`。
4. 初始设置区 `initial-setting` `#696b99`（紫灰）圆角顶部 `35rpx`：列表项图标圆底 `83rpx` + 名称白 `28rpx/500` + 介绍 `#a9abc5 21rpx` + 「去设置」粉按钮 `#ffe9f6/#dc3c5c`。
5. 底部 logo `bottom-logo`：`#f5f5f5` 高 `285rpx`，logo `182×67rpx` + 版本 `#a8a8a8 18rpx`。

### 5.6 登录页 `pages/login/index.vue`

- 对标为微信授权登录组件（`mpweixin`）。当前项目已有登录逻辑，UI 对标要点：全屏品牌背景（参考 `static/imgs/startlogo.png` 风格），居中 logo + 微信一键登录按钮（绿色 `#07c160` 或沿用品牌金）。


---

## 6. 需要新建的可复用组件（`src/components/`）

用 Vue3 SFC 重写以下对标组件，全部放 `src/components/`：

| 组件 | 对标 | 功能与样式要点 |
|---|---|---|
| `CustomNav.vue` | `components/navigation` | 自定义导航栏：fixed 顶，标题 `16px/700` 居中，左侧返回 `75rpx` 区，props: `text`/`bg`/`customBack` |
| `Nodata.vue` | `components/nodata` | 空状态：图 `171×203rpx` + 文字 `#bfbfbf 25rpx`，props: `msg`/`type` |
| `BottomLogo.vue` | `components/ff-bottom-logo` | 底部品牌留白：高 `285rpx`，logo `182×67rpx` + 版本号 `#a8a8a8 18rpx` |
| `ConfirmModal.vue` | `components/confirm-modal` | 模态：u-modal 宽 700，自绘标题/内容/取消+确定按钮（确定 `#fbd128`、取消白底描边） |
| `FfPopup.vue` | `components/ff-popup` | 底部弹层：u-popup bottom 圆角 20，可选返回箭头/标题/tips/scroll主体/确认槽 |
| `TimePicker.vue` | `components/time-picker` | 时间选择 |
| `SearchBar.vue` | 会员页搜索栏 | 金黄底胶囊搜索框 + 筛选按钮，emit `search`/`filter` |
| `MetricRow.vue` | 首页四指标 | 横排指标 + u-line 竖分隔 |
| `CardListItem.vue` | 售卡/约课项 | 头像 + 信息 + 标签 + 金额 + 底分割线 |
| `ExpireAlert.vue` | `components/expiredAlert` | 到期提醒弹窗 |

---

## 7. uView 1.x → uview-plus 迁移要点

两边都用 uView，组件名大多沿用，但 Vue3 版 props/事件有变化，逐个核对：

| 组件 | 1.x 用法 | uview-plus (Vue3) 注意 |
|---|---|---|
| `u-line` | `color/direction/length` | 基本一致，`direction="col"` 竖线 |
| `u-gap` | `bgColor/height` | 一致 |
| `u-divider` | `bgColor/borderColor/color/halfWidth` | 一致，用于「没有更多了」 |
| `u-tag` | `text/size/type` | 一致；新会员 `type=success`，自定义用 `bgColor`/`color` |
| `u-button` | `bind:click` | 改 `@click`；`hairLine`/`hoverClass` 仍可用 |
| `u-modal` | `bind:input`/`value` | 改 `v-model:show`；`showTitle`/`showConfirmButton` 一致 |
| `u-popup` | `bind:close`/`bind:input`/`value` | 改 `v-model:show` + `@close`；`mode/borderRadius/closeable` 一致 |
| `u-icon` | `bind:click`/`name/size` | 改 `@click`；`name=arrow-left` |
| `u-empty` | `mode/text` | 一致 |

- 事件绑定统一从 `bind:xxx` / `bindtap` 改为 Vue3 的 `@xxx`。
- `data-event-opts`、`bind:__l`、`vueId`、`vueSlots`、`data-v-*` 全是编译产物噪音，**忽略**，只看 `class` 与文本结构。

---

## 8. 执行顺序（建议）

1. **替换设计令牌**：更新 `src/styles/theme.scss` 为本规范第 2 节的令牌（金黄主色、橙营业额、灰阶），更新 `src/styles/common.scss` 的 `page` 背景为 `#f5f5f5`、`section-title` 为 `35rpx/500`。
2. **建公共组件**：先做 `CustomNav`/`Nodata`/`BottomLogo`/`ConfirmModal`/`FfPopup`/`SearchBar`（第 6 节）。
3. **改首页**：按 5.1 重写 `src/pages/index/index.vue` 模板+样式，逻辑保留。这是样板页，做完确认风格。
4. **推广到其余 tab 页**：课程页(5.2)、会员页(5.3)、报表页(5.4)、设置入口(5.5)。
5. **逐页对照子页**：按第 4 节映射表，把 `src/pages/settings/*`、`src/pages/members/*`、`src/pages/report/*` 子页换成对应风格。
6. **tabBar 图标**：替换 `src/static/tabbar/` 为对标风格图标（金黄选中态）。
7. **像素级验证**：微信开发者工具逐页截图比对，rpx 单位两边一致可直接对照。

---

## 9. 关键约束（执行 AI 必读）

- **只改 UI（template + style），不动逻辑**：`<script setup>` 里的权限判断 `session.can()`、API 调用 `src/api/*`、类型 `src/types/*`、Pinia store、`onShow`/`onPullDownRefresh` 全部原样保留。
- **主色是金黄 `#fbd128`/`#ffcf00`，不是蓝色**：当前项目的 `#1677ff` 蓝色令牌要整体替换。
- **核心数字是橙色 `#ed920f` 大号**：营业额、累计收款等。
- **统一"彩色顶栏 + 白色圆角内容区上压"布局**，全站一致。
- **单位用 `rpx`**，与对标一致，便于像素级还原。
- **忽略编译产物噪音**：`data-v-*`、`data-event-opts`、`__l`、`vueId`、`vueSlots` 一律忽略。
- 图标/图片：优先复用对标 `static/` 风格资源，或用 uview-plus `u-icon` 替代。

