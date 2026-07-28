# 新管理端（staff-miniapp）对标原版差距分析

> 基准：`管理端/`（原版编译产物，264 个真实接口已提取至 `docs/原版管理端接口清单.txt`）
> 对象：`songguo-next/apps/staff-miniapp`（67 页）+ `songguo-next/apps/server`（Laravel 11，228+ 接口）
> 参考：`docs/traceability-apis.csv`（313 条映射：210 REPLACE / 45 MERGE / 9 REJECT）、`docs/traceability-pages.csv`
> 日期：2026-07-26

## 一、UI 样式差距（对标优化项）

### U1. TabBar 结构 【最大结构差异】
| | 原版 | 新版 |
|---|---|---|
| Tab 数 | **5**：今天 / 课程 / 会员 / 报表 / 场馆 | **2**：工作台 / 课程 |
| 配色 | 白底，未选中 #505050，选中 **#181818**（黑） | 白底，未选中 #667085，选中 **#1677ff**（蓝） |

新版把会员/报表/设置折叠进工作台九宫格，多一层跳转。对标应恢复 5 tab。

### U2. 主题色体系
- 原版：黑白灰基调（#181818/#505050/#989898/#bfbfbf），**橙 #ed920f 为金额/主操作色**（浮动按钮、营业额大字），功能色 绿#22c788（新会员/成功）、蓝#5fa3ea、红#dc3c5c/#e77a76、深橙#f88302。
- 新版：单一蓝 #1677ff 企业后台风（theme.scss：success #16a36a / warning #d97706 / danger #d92d20）。

### U3. 首页（今天/工作台）布局
原版（pages/home/home）：
1. 顶部背景头图区 310rpx（home_top_bg.jpg，fixed），白字问候 53rpx bold + 副语 22rpx，左上「场馆切换」胶囊（图标 38×42rpx + 白字 28rpx）。
2. 白色内容区 `margin-top:-20rpx; border-radius:20rpx 20rpx 0 0` 上盖头图。
3. 今日营业额 **#ed920f 90rpx** 大字 + 睁/闭眼切换。
4. 4 指标横排（预约团课/预约私教/售卡/新增会员），#DDDDDD 竖分隔线。
5. 「今日售卡」列表（头像、姓名、¥金额、时间、新会员绿标 #22c788、支付方式）。
6. 「今日约课」列表（课程/讲师/时间/状态）。
7. 底部固定「关注公众号」浮条（#e77a76 底、圆角 21rpx、白色按钮字 #dc3c5c）。

新版（pages/index/index.vue）：无头图、无问候区、平铺白卡、无渐变；功能等价但视觉简陋。

### U4. 通用样式 token
| Token | 原版 | 新版 |
|---|---|---|
| 页面背景 | #F5F5F5 | #f4f6f8（近似，可统一） |
| 导航栏 | #F5F5F5 黑字 | #F8F8F8 黑字（近似） |
| 大卡圆角 | 16–21rpx | 4–8rpx（$radius-sm/md，偏方正） |
| 按钮圆角 | 30–35rpx 胶囊 | uView 默认 |
| 大标题/金额字号 | 53rpx / 90rpx | 无此层级 |
| 浮动新增按钮 | 橙 #ed920f 圆形（会员页） | 无 |
| 空状态 | 插图 203×171rpx + #bfbfbf 文案 | u-empty 默认 |

### U5. 课程页
原版：顶部**私教教练横滚区**（头像+姓名+N人预约+「不指定」）→ 周日历 → 团课课程卡（难度★、场地、讲师头像、预约头像墙、状态角标：已约满/已停课/已截止/上课中/已下课/代排队）。
新版：仅日期选择 + daily-board 列表，无私教横滚区、无预约头像墙、状态样式简单。

### U6. 会员页
原版：搜索框+筛选 → 会员卡片（头像、新会员/VIP 标签、电话、卡券摘要、有效期、操作菜单）→ 橙色圆形浮动「+」。
新版：统计 chips + 搜索 + 拼音导航 + 列表行（功能全，样式平）。

## 二、功能 / 接口缺口

### A 类：后端已有接口，前端未实现（补前端即可）
| # | 缺失功能 | 原版依据 | 后端已有接口 |
|---|---|---|---|
| A1 | **会员积分**：详情页积分入口、积分调整（加/减）、积分规则配置页 | pageMember/details/memberPoint、pageReport/rank/memberPointConfig（addUserPoint/substractUserPoint/getUserPointConfig/saveUserPointConfig/PointListByUserId） | POST `/staff/sites/{site}/members/{member}/point-adjustments`；GET/PUT `/staff/sites/{site}/points-config`（api.php:264-265,286）；排行已有 |
| A2 | **平台订阅购买/续费流程**（价目表→协议→微信支付） | pageServer/order（pricelist/getAgreement/submitwexinOrder/findserviceSuccessOrder）+ expiredAlert 过期拦截组件 | GET pricing / GET agreement / POST pay / GET status（api.php:165-168）；前端仅接了 orders 列表 |
| A3 | **私教教练视图**：课程页私教横滚 + 按教练看周私教预约（代约/取消/旷课/改约/备注） | pages/course 私教区、pagesCourse/personalTrainerDetails（findAllPrivateDrainerList/getDrainerTimeList2/findOneDrainerAppointment） | 可组合现有 schedule-sessions（coach 过滤）+ session appointments + 各预约操作接口 |
| A4 | 今日约课「upcoming」视图 | pages/home selectAppoint | GET `/staff/sites/{site}/booking/upcoming` 已有，前端工作台目前用 appointment-feed 近似（弱缺口） |

### B 类：前后端都缺（需要后端+前端）
| # | 缺失功能 | 原版依据 | 现状 |
|---|---|---|---|
| B1 | **变更记录/操作日志**（发卡、请假、停卡、删卡、场馆操作日志，可按员工筛选） | pageReport/rank/siteModifyLog（FindsiteModifyLog/getsiteModifyType）、findModifyLog | `audit_events` 表已存在，但无 staff 查询接口、无页面 |
| B2 | **会员分析报表**：有效/无效/风险(60天未上课)/沉寂(90天)/流失(120天)/上月上课/本月上课/无卡/屏蔽 分层钻取 | pageReport/memberAnalyze/allMember | 新版会员页 dashboard 仅 6 个简单 chips；缺报表页与风险/沉寂/流失口径接口 |
| B3 | **会员卡分析**：全部/有效/无效/已过期/余额0/过期有余额/未开卡/请假中/停卡中 9 分层 + **资产负债表**（总收入/已耗卡金额/剩余价值）+ 整店重算（每日限5次） | pages/report（UserCardAnalyze/computeAgain）、memberCardAnalyze | 新版有 card-product-analytics（按卡种），缺按状态分层与资产负债汇总；重算仅工资 |
| B4 | **课表导出图片** | /b/arrange/getArrangeImage | 后端 `ScheduleExportImageService` 返回 placeholder，未真正生成 |
| B5 | 卡种手动排序 | /b/card/saveSortId | 后端 card-products 无排序接口（低优） |

### C 类：有意不做（前期评审已 REJECT，共 9 项，不补）
物理删除会员/订单（改软删/作废）、积分清零与删积分日志（改冲正调整）、mergeOpentime、getOne(staff)、getAllCardForHasAgreement、dict 等无调用点的僵尸导出、静态公众号二维码页、league 孤儿报表页×2。

### D 类：占位待接入（前端已有壳）
- 视频帮助 CDN 为演示占位（settings/support/video-help）。
- 工作台部分快捷入口 toast「暂未开放」（pages/index/index.vue:195）。

## 三、建议实施顺序
1. **P0 UI 对标**（用户主诉求）：全局主题（色板/圆角/字号）→ 5 TabBar → 首页重做 → 课程页（私教横滚+课卡样式）→ 会员页样式。
2. **P1 前端补齐**（A 类）：积分（A1）→ 订阅购买（A2）→ 私教视图（A3，与课程页改版合并做）。
3. **P2 前后端补齐**（B 类）：操作日志（B1）→ 会员分析（B2）→ 会员卡分析/资产负债（B3）。
4. **P3**：课表图片导出（B4）、卡排序（B5）、视频 CDN（D）。
