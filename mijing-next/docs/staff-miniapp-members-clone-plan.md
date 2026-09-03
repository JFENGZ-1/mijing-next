# pages/members/index 完整复刻规划

> 原版：`管理端/pages/member/member`
> 新版：`mijing-next/apps/staff-miniapp/src/pages/members/index.vue`
> 对标原则：所有功能、所有按钮路径与打开的页面路径全部复刻（memory：功能范围决策原则）

---

## 1. 原版 wxml 树（已解构）

```
<page-meta overflow>                // 全局 overflow 控制（弹窗时锁滚动）
<view class="forbidScroll?">        // 同上
  <!-- 顶部固定头 -->
  <view max-fixed-box>               // z-index:50，弹窗时升级
    <view class="fixed-box">         // background:#fbd128, fixed top, h=StatusBar+CustomBar+110rpx
      <view class="cu-status" h=StatusBar>           // 状态栏占位
      <view class="cu-capsule" h=CustomBar>会员管理</view> // 标题栏 44rpx font-weight 700
      <view class="top-search-box" h=110rpx>          // 搜索栏区
        <view class="search-box-flex">
          <view class="search-content">
            <view class="input-box" @tap=headleSearch> // → /pageMember/search
              <image search_icon>
              <text>会员名/手机号</text>
            </view>
            <view class="headbut">
              <view class="filter" wx:if=canBatch @tap=headleScreen(1)>
                <image userlist_icon /> <text>批量</text>     // 仅管理员（hasPermission 58）显示
              </view>
              <view class="filter" @tap=headleScreen(2)>
                <image member_filter_icon /> <text>筛选</text> // → /pageMember/screen?num=2
              </view>
            </view>
          </view>
        </view>
      </view>
    </view>
  </view>

  <!-- 占位 + 固定加载圈 -->
  <view class="placeholder-view" h=StatusBar+CustomBar+110rpx>   // 占位
  <view class="fixed-loading-wrap">                              // 中部圆点
    <view class="fixed-loading"></view>
  </view>

  <!-- 数据区 -->
  <view class="member-data">                                     // 白底圆角卡片
    <view class="help_img" @tap=openData>                       // 右上角 ? 图标
      <image usertip />
    </view>
    <view class="top-data">                                      // 第 1 行：全部会员 / 本月新增
      <view class="item" @tap=headleFilter(0)>全部会员 / {{reportData.totalCount}}</view>
      <u-line color="#F0F0F0" direction=col length=63rpx>
      <view class="item" @tap=headleFilter(11)>本月新增 / {{reportData.monthCount}}</view>
    </view>
    <view class="data-box">                                      // 第 2 行：4 项 + 更多分析
      <view class="item" @tap=headleFilter(1)>有效会员 / {{reportData.validUserCount}}</view>
      <u-line>
      <view class="item" @tap=headleFilter(2)>无效会员 / {{reportData.invalidUserCount}}</view>
      <u-line>
      <view class="item" @tap=headleFilter(9)>无卡/访客 / {{reportData.nocardUserCount}}</view>
      <u-line>
      <view class="item" @tap=headleFilter(10)>屏蔽会员 / {{reportData.nologinUserCount}}</view>
      <u-line>
      <view class="item" @tap=headleFilter(6)>                  // → /pages/report/report
        <image usermore><view>更多分析</view>
      </view>
    </view>
  </view>
  <u-gap bgColor=#f5f5f5 height=24>

  <!-- 列表区 -->
  <view class="member-list">
    <view class="user-list" wx:if="!noData&&hasMemberPermission">
      <view class="filter">                                     // 排序筛选行
        <view class="left-box">
          <u-checkbox-group>
            <u-checkbox shape=circle size=30 v-model=isUser
              @change=changeUser>含无卡/访客</u-checkbox>
          </u-checkbox-group>
        </view>
        <view class="right-box">
          <view class="item" v-for="rightList" @tap=headleRanking(rightList)>
            <text>{{rightList.name}}</text>
            <view class="triangle" />
            <u-icon name=arrow-right size=16 color=#989898 />
          </view>
        </view>
      </view>
      <view class="loading-wrap" wx:if=首次加载>
        <loading-pulse />
      </view>
      <u-index-list
        :indexList="indexList"
        :scrollTop="scrollTop"
        :sticky="false"
        :activeIndex="activeIndex"
        @select="changeChat"
        @activeIndex="activeIndex"
        zIndex=99
      >
        <view wx:for="list" wx:key=letter>
          <u-index-anchor :index="letter">{{letter}}</u-index-anchor>
          <view class="member-item" wx:for="list[letter].data" wx:key=userId>
            <view class="avatar">
              <image @tap=headleDetails(userId)
                :class="{grey: noLogin==1}"
                :src="userFaceurl"
              />
              <view wx:if=noLogin==1 class="forbidden-img">
                <image forbidden.png w=26 h=26 />
              </view>
            </view>
            <view class="content">
              <view class="center" @tap=headleDetails(userId)>
                <view class="top-info">
                  <text class="name">{{userRealname}}</text>
                  <image remark.png wx:if=hasremark==1 />
                  <image tag.png wx:if=tagValue==1~5 />
                </view>
                <view class="bottom-info">
                  <text class="date">{{lastAppointDate}}</text>
                  <text class="surplus" wx:if=cardType==1>余{{balanceAmount}}元</text>
                  <text class="surplus" wx:elif=cardType==2>余{{balanceAmount}}次</text>
                  <text class="surplus" wx:else wx:if=cardType==3>余{{balanceAmount}}天</text>
                  <view class="status" wx:if=holidayDate>
                    <image renew-icon><view class="type-name">{{holidayDate}}</view>
                  </view>
                  <view class="hintmsg" wx:if=hintMsg>
                    <image triangle-icon><view class="type-name">{{hintMsg}}</view>
                  </view>
                </view>
              </view>
              <view class="card-type" @tap=headnleCard(userId)>
                <image single-icon wx:if=cardCount==1 />
                <image multi-icon wx:if=cardCount>1 />
                <image no_card_type wx:if=cardCount==0 />
              </view>
            </view>
          </view>
        </view>
      </u-index-list>
    </view>

    <view class="noCourseData" wx:else>
      <image nodata>
      <view wx:if=!hasMemberPermission>仅管理员可见</view>
      <view wx:else>
        <view class="add_btn">点击右下方"添加"按钮</view>
        <view class="add_btns">录入场馆的会员</view>
      </view>
    </view>
    <fixed-btn wx:if="!hasPermission(58)">添加</fixed-btn>     // 仅普通员工可添加
  </view>

  <ff-bottom-logo />

  <!-- 抽屉 / 弹窗 -->
  <member-details ref="cardIndexRef" @memberUpdate @headleClose />   // 卡抽屉
  <u-modal title="数据说明：" v-model=showData width=681
    confirmText=知道了 @confirm=confirmKnow>
    <rich-text nodes=content />
  </u-modal>
  <expired-alert page=other />                                    // 全局过期提醒
  <view class="max-mask" wx:if=!hasMemberPermission />             // 锁屏遮罩
</view>
```

---

## 2. 原版 js 关键行为摘要

| 方法 | 行为 | 目标 |
|---|---|---|
| onLoad | 读取 `MEMBER_ISUSER` 缓存；初始化 isUser；有权限则加载卡列表（getCardList → getAllCardInfo） | / |
| onShow | shopInfo；cardIndexRef.reload；headleEmpty（清空筛选选择态）；loadFindUser(1)；modifidStatus | / |
| onPageScroll | scrollTop = scrollTop - 160（节流 50ms） | 给 u-index-list |
| onPullDownRefresh | loadFindUser() | / |
| `loadPinYinList` | `sumReport()` → `indexList` + `reportData`；按 pinyinlist 创建 list 占位 | 首次 |
| `loadFindUser` | 卡片/标签/备注/请假/停卡筛选 → sumReport → findAllUser → 按 pingyinChar 重新分桶 → `reportData = data`；totalCount > 300 启用子步加载 | / |
| `getdata` | 按需为某个字母分桶加载（子步） | 滚动到字母时 |
| `getIsSubstepPinYin` | 从当前字母往后取 ≤3 个累计 ≤300 会员 | / |
| `changeUser` | isUser 反转 → 写 storage → cardCountTag 切换（0=含无卡 / 1=不含） → loadFindUser(1) | / |
| `headleSearch` | navigateTo `/pageMember/search` | 搜索 |
| `headleScreen(1)` | navigateTo `/pageMember/screen?num=1` | 批量 |
| `headleScreen(2)` | navigateTo `/pageMember/screen?num=2` | 筛选 |
| `headleFilter(flag)` | flag=6 → switchTab report；其它 → `/pageMember/screen?flag=N` | 顶部数据卡 |
| `headleRanking(item)` | id=4 → `/pageMember/del-member/del-member`；其它 → `/pageMember/screen?rightListId=N` | 删除记录 |
| `headleDetails(userId)` | href `/pageMember/details/index?userId=N` | 会员详情 |
| `headnleCard(userId)` | `cardIndexRef.open({userId})` + `cardFlag=true` | 卡抽屉 |
| `Click`（fixed-btn） | 访问者 → 授权；否则 → `/pageMember/information/index` | 添加会员 |
| `openData` | showData=true; cardFlag=true | 数据说明弹窗 |
| `confirmKnow` | cardFlag=false | / |
| `memberUpdate` | headleEmpty + loadFindUser(1) + modifidStatus | 抽屉变更后刷新 |
| `activeIndex` | 字母变化 → pinindex / pinyinindex → 子步时 getdata | u-index-list |
| `changeChat` | select 触发 → activeStatus=1（短窗防抖）；处理字母 | u-index-list |
| `headleCardSubmit(usercardId)` | `delUserCard` → loadFindUser + toast | 删卡 |
| `headleRemarks / headleFlag / headleNoCard / cardClick / handleCancelbtn / handleDeterminebtn / modifidStatus` | 筛选状态交互 | / |

---

## 3. 原版 data 字段（语义 + 来源）

| 字段 | 用途 | 现状（新版） |
|---|---|---|
| `hasMemberPermission` | store getter getUserFunc(31) | `session.can("crm.member.read")` |
| `reportData` | `{ totalCount, monthCount, validUserCount, invalidUserCount, nocardUserCount, nologinUserCount }` | `dashboard: CrmDashboardSummary` |
| `indexList / list / pinyinlist` | 字母分组 + 行 | `indexList + groups` |
| `isUser` | 含无卡/访客（含可约会员） | `includeVisitors` |
| `noData` | 列表空 | `!hasData` |
| `cardFlag / show` | 全局滚动锁 | `page-meta overflow` (新版由 uview 弹窗自管) |
| `rightList` | 右上行 `{name,id}`（含「删除记录」） | 已实现 |
| `btnList / flagList / remarksList / noCard` | 筛选预设 | 落在 `filter.vue` |
| `scrollTop` | 给 u-index-list | 暂未传（新版用 anchor scroll） |
| `activeStatus / pinyinindex / pinindex / isSubstep / pinyinli / pinyin` | 子步加载状态机 | `loadedLetters + loadMoreLetters`（批 3 字母） |
| `cardList / flagList / remarksList / cardStore / flagStore / remakeStore / leaveStore / stoppingStore / cardCountTag` | 筛选缓存 | 在新版 filter 页面 + storage `crm_member_list_filters` |
| `content` | 数据说明富文本 | 已落到 u-modal 文案 |

---

## 4. 原版 vs 新版按钮路径对照

| 原版 wxml 触发 | 原版路径 | 新版路径 | 现状 |
|---|---|---|---|
| `headleSearch` | `/pageMember/search` | `pages/members/search` | 已存在 |
| `headleScreen(1)` 批量 | `/pageMember/screen?num=1` | `pages/members/batch-ops` | 已存在 |
| `headleScreen(2)` 筛选 | `/pageMember/screen?num=2` | `pages/members/filter` | 已存在 |
| `headleFilter(0..11)` | `/pageMember/screen?flag=N` | `pages/members/filter`（持久化 storage） | OK |
| `headleFilter(6)` 更多分析 | `/pages/report/report` | `pages/report/member-analyze/index` | OK |
| `headleRanking(id=4)` 删除记录 | `/pageMember/del-member/del-member` | `pages/members/deleted` | 已存在 |
| `headleDetails(userId)` | `/pageMember/details/index?userId=N` | `pages/members/detail?id=N` | 已存在 |
| `headnleCard(userId)` | 抽屉 member-details | `member-card-sheet` 组件 | 已实现 |
| `Click`（fixed-btn） | `/pageMember/information/index` | `pages/members/form` | 已存在 |
| `openData` 数据说明 | 弹窗 | `u-modal` | 已实现 |

> 所有按钮路径已经 1:1 对齐。

---

## 5. 新版缺失/差异项（要点修复）

### 已对齐 ✅
- 顶部 fixed-box 黄色 #FBD128 头部（statusBar + 标题 + 搜索栏）
- 数据卡 6 项 + 4 u-line 分隔
- u-index-list 字母分组 + 字母锚点滚动 + 侧边字母栏（新版用自渲染 `.index-bar`）
- 会员卡行：头像 / 灰 + 锁标（`appAccessStatus=blocked`）/ 姓名 / 备注点 / 标签点 / 状态标签（潜客/正式/冻结/已关闭）
- 余额卡：根据 `cardType` 切换「余X元/次/天」单元
- 多/单/无卡图标：单卡 `integral-fill` / 多卡 `grid-fill` / 无卡 `minus-circle`（颜色 #ed920f / #d8d8d8）
- 含无卡/访客 u-checkbox（持久化 `MEMBER_ISUSER`）
- 数据说明 u-modal
- 无权限 max-mask
- 添加按钮（fixed-btn）

### 仍需补充 ⚠
1. **`holidayDate` 与 `hintMsg` 行内提示**：原版底部信息行有「假期 / 备注」橙色 chip + 三角标，对应后端 listItemSummaries 当前未返回；类型 `CrmMember` 也无 `holidayDate` / `hintMsg` 字段。**不阻塞 UI**，先把字段加到类型；后端给空时自动隐藏即可。
2. **`lastAppointDate` 缺省文案**：原版空时同样空（行内 `~ 暂无约课 ~` 之类），新版用 `"暂无约课"` 占位，已对齐。
3. **排序行右侧 `rightList` 默认 `[{name:'删除记录'}]`**：新版已实现 `deleted-entry`（u-icon arrow-right）。✅
4. **active 高亮**：原版数据卡未高亮选中态，新版自加 active 高亮（#fff8e6 + #ed920f）— 不冲突原版，作为可读性增强保留。
5. **`expired-alert`** 组件：原版全局过期提醒。新版通过 store/session 处理；不强行引入。
6. **顶部状态栏 u-icon `grid-fill` / `list-dot` 占位图**：原版用本地 png 资源（`imgs/202501/userlist.png`、`member_filter_icon.png`）。新版用 u-icon 语义图标替代，效果等价但视觉略有差异。如需 1:1 可加 `static/member/` 资源 — 非阻塞。
7. **底部 `ff-bottom-logo` 组件**：新版已引入同名组件，复用。✅
8. **`expired-alert` 的 page=other**：原版做全局提示；新版若需要可在 onShow 调 store action，暂不强行引入。

### 后端字段补强（非阻塞）
- 给 `StaffCrmMemberListService::listItemSummaries` 加 `holidayDate` 与 `hintMsg`：
  - `holidayDate`：若会员在请假中（member_vacations active）→ 显示"请假至 YYYY-MM-DD"
  - `hintMsg`：若有最新备注 → 显示"备注: ..." 否则 null
- 类型 `CrmMember` 加 `holidayDate?: string | null; hintMsg?: string | null;`

---

## 6. 数据契约（已在后端落地）

### Dashboard Summary
- 端点：`GET /api/v1/sites/{site}/crm/dashboard`
- 响应：`{ totalCount, monthCount, validUserCount, invalidUserCount, nocardUserCount, nologinUserCount, pinyinIndex: [{initial, count, pingyinChar, ncount}] }`

### 会员列表
- 端点：`GET /api/v1/sites/{site}/crm/members?page=&perPage=&pinyinInitial=&sumMode=&runOff=&flag=&includeVisitors=`
- 行摘要：`{ id, memberNo, name, avatarUrl, pinyinInitial, cardCount, cardType, balanceAmount, balanceUnit, lastAppointDate, status, appAccessStatus, hasStickyRemark, tags: [{id,name,color}], owner: {id,name} }`
- 分页：`{ page, perPage, total, lastPage }`

### 删除会员
- 端点：`GET /api/v1/sites/{site}/crm/deleted-members` + `POST .../restore`

### 筛选预设
- 端点：`GET /api/v1/sites/{site}/crm/filter-presets`
- 响应：`{ sumModePresets, flagPresets, runOffPresets }`

---

## 7. 执行计划（增量改动）

按"按钮路径 1:1 + 缺字段补类型 + UI 微调"三步执行：

1. **types/crm.ts**：增加 `holidayDate?: string | null; hintMsg?: string | null;`
2. **pages/members/index.vue**：
   - 卡片行增加 `holidayDate` / `hintMsg` chip（仅在值存在时显示），样式参考原版 `bottom-info > status > type-name`（#ffeae1 / #ee8231，圆角）
   - 字母锚点滚动当前用 `scrollInto`，与原版 `u-index-list @select` 不同；保留现有实现（更稳定）
   - 「删除记录」入口加 `appAccessStatus=blocked` 灰色头像时的 forbidden-badge 锁标（已有）
3. **后端非阻塞补强**：listItemSummaries 加 holidayDate/hintMsg（条件：member_vacations 命中 / 备注表最新），不影响前端渲染。

执行 → 检查 → 提交 checkpoint。

---

## 8. 当前落地状态（2026-08-22）

- `holidayDate` 已由有效会员卡 `freeze_state.holiday.plannedEndAt` 汇总返回；多张卡取最晚的有效结束日。
- `hintMsg` 已接通会员最新备注，并受 `crm.member.note.read` 权限保护；无权限时返回 `null`。
- 列表控制器已透传两个字段，前端行内 chip 与类型定义已接通。
- 后端相关测试、PHP 语法/格式检查、员工端 TypeScript 检查均通过；微信开发者工具视觉验收仍需在授权连接后执行。
