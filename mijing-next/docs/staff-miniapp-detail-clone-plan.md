# pages/members/detail 完整复刻规划

> 原版：`管理端/pageMember/details/index`
> 新版：`mijing-next/apps/staff-miniapp/src/pages/members/detail.vue`
> 对标原则：所有功能、所有按钮路径全部复刻（memory：功能范围决策原则）

---

## 1. 原版 UI 结构（已解构）

### 1.1 整体布局

```
<view>
  <view class="container">
    <!-- 顶部沉浸区：fixed，背景模糊头像，导航栏 + 个人信息卡片 -->
    <view class="personalTainerModule" h=StatusBar+CustomBar+headerH>
      <!-- 滚动时渐变透明导航栏（fixed + opacity 跟随 scrollTop） -->
      <view class="info-module nav_floating" fixed>
        <view h=StatusBar />  ← 状态栏占位
        <view class="capsule-wrap" h=CustomBar>
          <view class="back" @tap=back>  ← 返回
            <image back.png />
          </view>
          <view class="head-img">  ← 导航栏内头像+姓名
            <image class="img-head" :src="personalTainerInfo.userFaceurl" />
            <text class="head-realName">{{nameText}}</text>
          </view>
        </view>
      </view>

      <!-- 非固定主体区（内容随滚动超出） -->
      <view class="info-module">
        <view h=StatusBar />  ← 占位
        <view class="capsule-wrap" h=CustomBar>
          <view class="back" @tap=back>
            <image back.png />
          </view>
        </view>
        <!-- 背景：头像模糊放大（filter:blur 25rpx + scale 1.3）+ 半透明遮罩 -->
        <view class="{{photo-filter|photo-filter-grey}}"
          h=StatusBar+CustomBar+headerH
          :style="'background:url('+personalTainerInfo.userFaceurl+') ...'"
        >
          <view opacity=0.3 />
        </view>

        <view class="wrap">
          <!-- 个人信息卡片（居中） -->
          <view class="center-wrap">
            <view class="info-wrap">
              <view class="photo-wrap">
                <image class="{{photo-image|photo-image-grey}}"
                  :src="personalTainerInfo.userFaceurl" />
                <view wx:if=noLogin==1 class="forbidden-img">
                  <image forbidden.png w=44 h=44 />  ← 屏蔽锁标
                </view>
              </view>
              <view class="name">{{nameText}}</view>  ← 姓名/昵称/手机尾号
              <!-- 标签 + 设置按钮行 -->
              <view class="handle-wrap">
                <view style="display:flex;">
                  <view class="img-wrap" @tap=headleMark>  ← 改标签
                    <image wx:if=tagValue==0 tagging.png />
                    <image wx:if=tagValue==1 red_flag.png />
                    ...（5色旗）
                  </view>
                  <view wx:if="!hasPermission(58)" class="img-wrap first" @tap=headleSetUp>
                    <image set_up.png />  ← 设置（编辑资料）
                  </view>
                </view>
                <view style="display:flex;justify-content:flex-end;" wx:if="!hasPermission(59)">
                  <view class="img-wrap first img-wrap-top" @tap=changeShowDrop>
                    <image tags1.png />  ← 更多（⋮ 下拉）
                  </view>
                </view>
                <!-- 下拉菜单（changeShowDrop1） -->
                <view class="drop_down" hidden="{{!changeShowDrop1}}">
                  <view wx:if=noLogin==0 @tap=forbidden(true)>
                    <image forbidden_icon.png />屏蔽访问
                  </view>
                  <view wx:else @tap=forbidden>
                    <image undisable.png />取消屏蔽
                  </view>
                  <view @tap=makeOver>
                    <image make_over.png />转让账号
                  </view>
                </view>
              </view>
            </view>
          </view>

          <!-- 信息区（手机/姓名/昵称/性别/生日/身份证/身高/体重/会籍顾问） -->
          <view class="information">
            <view class="information_phone">
              <view wx:if=checkUserField(1) @tap=freeTell>
                手机号：{{personalTainerInfo.userPhone}}
              </view>
              <view wx:if=checkUserField(2)>姓 名：{{personalTainerInfo.userRealname}}</view>
              <view>昵 称：{{personalTainerInfo.userNickname}}</view>
              <view wx:if=checkUserField(3)>性 别：{{personalTainerInfo.userSexName}}</view>
              <view wx:if=checkUserField(4)>生 日：{{birthday}}</view>
              <view wx:if=checkUserField(5)>身份证：{{personalTainerInfo.userIdent}}</view>
              <view wx:if=checkUserField(6)>身 高：{{personalTainerInfo.userHeight}}</view>
              <view wx:if=checkUserField(7)>体 重：{{personalTainerInfo.userWeight}}</view>
              <view wx:if=checkUserField(8)>会 籍：{{personalTainerInfo.salestaffuserName}}</view>
            </view>
            <!-- 会员备注行 -->
            <view class="information_remarks">
              <text>备注：</text>
              <view class="remarks">{{personalTainerInfo.userRemark}}</view>
              <view wx:if="!hasPermission(58)" class="icon-wrap" @tap=headleRemarks>
                <image remarks.png />  ← 编辑备注弹窗
              </view>
            </view>
            <!-- 流失预警图（noClassDays 1~4=30/60/90/120天未上课） -->
            <image wx:if=noClassDays==1 member_status_30.png />
            <image wx:if=noClassDays==2 member_status_60.png />
            <image wx:if=noClassDays==3 member_status_90.png />
            <image wx:if=noClassDays==4 member_status_120.png />
          </view>
        </view>
      </view>
    </view>

    <!-- 下方列表区（白色内容） -->
    <view class="list-wrap">
      <!-- 横向滚动指标卡 -->
      <scroll-view scroll-x>
        <view class="appointment-info">
          <view @tap=rechargeAmount>
            <view>{{totalPayAmount.total_pay_amount|'--'}}</view>
            <view>累计</view><view>总消费(元)</view>
          </view>
          <u-line>
          <view @tap=courseDetail>  ← 团课详情
            <view>{{teamclass_month_count}}/{{teamclass_total_count}}</view>
            <view>本月/累计</view><view>团课</view>
          </view>
          <u-line>
          <view @tap=personalDetail>  ← 私教详情
            <view>{{priclass_month_count}}/{{priclass_total_count}}</view>
            <view>本月/累计</view><view>私教</view>
          </view>
          <u-line>
          <view wx:if=pointStart==1 @tap=pointDetail>  ← 积分详情
            <view>{{last_month_point}}/{{total_point}}</view>
            <view>本月/累计</view><view>积分</view>
          </view>
          <u-line wx:if=pointStart==1>
          <view @tap=truantDetail>  ← 旷课详情
            <view>{{absence_count}}/{{absent_count_total}}</view>
            <view>本月/累计</view><view>旷课</view>
          </view>
          <u-line>
          <view>  ← 无跳转
            <view>{{expend_price|'--'}}</view>
            <view>合计</view><view>已耗卡</view>
          </view>
          <u-line>
          <view>  ← 无跳转
            <view>{{left_price|'--'}}</view>
            <view>合计</view><view>剩余价值</view>
          </view>
        </view>
      </scroll-view>

      <!-- 领卡提示条（未领卡+未超管） -->
      <view wx:if="!unionid && cardlist.length>0 && !hasPermission(58)" class="notice-card">
        <view @tap=headleReceive>该会员还没领取此卡，通知会员领取→</view>
      </view>

      <!-- 卡标题行：共N张卡 + 删除的卡 -->
      <view class="new_card" wx:if=cardlist.length!=0>
        <view class="center">共{{cardlist.length}}张卡</view>
        <view wx:if=dellist.length>0 class="center" @tap=recycle>
          删除的卡 →
        </view>
      </view>
      <!-- 无卡空态 -->
      <view class="no_card" wx:else>
        <image membership_card.png />
        <view wx:if=dellist.length>0 @tap=recycle>删除的卡 →</view>
      </view>

      <!-- 会员卡列表（member-card 组件 + 卡备注 + 查看更多） -->
      <view class="cardList" wx:if=cardlist.length!=0>
        <view class="card_face">
          <!-- 有余额/未过期卡 -->
          <block wx:if=isshowCardmore>
            <view wx:for="cardlist有余额" wx:key>
              <member-card :cardInfo="item" @moreProject  ← 查看支持课程
                @tap=toggleCard(item)  ← 跳转卡详情
              />
              <view wx:if=item.cardRemark @tap=remarkCard(item)>
                备注：{{item.cardRemark前45字}}
              </view>
            </view>
          </block>
          <u-divider wx:if=isshowmore>已无余额或已过期</u-divider>
          <!-- 无余额/已过期卡（折叠） -->
          <block wx:if=isshowmore>
            <view wx:for="cardlist无余额" wx:key>
              <member-card :cardInfo="item" @moreProject @tap=toggleCard(item) />
              <view wx:if=item.cardRemark @tap=remarkCard(item)>
                备注：{{item.cardRemark前45字}}
              </view>
            </view>
          </block>
          <view class="click-description">点击会员卡，查看更多信息</view>
        </view>
      </view>

      <!-- 发卡按钮（仅非管理员可见） -->
      <view wx:if="!hasPermission(58) && !hasPermission(59)" class="create-card" @tap=headleNewCard>
        <text>发卡</text>
      </view>
    </view>
  </view>

  <!-- 弹窗/抽屉 -->
  <remarks ref=remarksRef @remarksSubmit />         ← 会员备注编辑
  <mark-pop ref=markpopRef @radioGroupSubmit />     ← 标签选择（6色）
  <member-cards ref=membercardRef                  ← 卡操作抽屉（删除/充值/停卡/请假）
    @submit=headleDelUserCard
    @aa=headleRecordList
    @updateDetailsPage
  />
  <confirm-modal ref=confirmModal title=领卡>        ← 领卡弹窗（二维码+转发）
    slot=content: 二维码 + 转发/自领按钮
    slot=btn: 关闭
  </confirm-modal>
  <confirm-modal ref=addconfirmModal title=文字>    ← 复制文字弹窗
    slot=content: oneList/twoList/threeList+手机号
    slot=btn: 复制并关闭
  </confirm-modal>
  <new-card ref=newcardRef @submit />               ← 发卡弹窗
  <make-over ref=makeOverRef @submit />             ← 转让账号弹窗
  <card-all-project ref=cardAllProject />           ← 卡支持课程弹窗
  <remark-order-popup ref=remarkAppointment />      ← 预约备注（内部）
  <remark-order-card-popup ref=remarkAppointmentCard /> ← 卡备注编辑（内部）
  <confirm-modal ref=forbiddenConfirmModal title=屏蔽确认> ← 屏蔽二次确认
  <ff-bottom-logo />
</view>
```

---

## 2. 原版关键行为（js methods）

| 方法 | 行为 |
|---|---|
| `onLoad` | `parameter.userId = e.userId` |
| `onShow` | `getlist + getSumCardInfo`（刷新数据） |
| `onPageScroll` | scrollTop <180 → opacity=0；180~200 → 线性插值；>200 → opacity=1 |
| `getlist` | `getUserCardInfo` → personalTainerInfo + cardlist + userField + dellist + noLogin + userId + userFaceurl；计算 top（卡片叠压偏移） |
| `getSumCardInfo` | `getSumCardInfo` → totalPayAmount（消费/团课/私教/积分/旷课/已耗卡/剩余价值） |
| `back` | `navigateBack` |
| `freeTell` | `makePhoneCall` 拨打电话 |
| `headleMark` | 改标签（无权限 58 才可操作）→ `markpopRef.open()` |
| `headleSetUp` | → `/pageMember/information/index?userId=N`（编辑资料） |
| `changeShowDrop` | 下拉菜单显隐 toggle |
| `forbidden(confirm?)` | confirm=true → 弹窗确认；confirm=false → 直接调 API |
| `forbiddenConfirmBtn` | `setUserNoLogin` → 屏蔽/取消屏蔽 |
| `makeOver` | → `makeOverRef.open(userId)`（转让账号） |
| `headleRemarks` | → `remarksRef.open()`（会员备注） |
| `remarksSubmit(remark)` | `updateUserRemark` → refresh getlist |
| `radioGroupSubmit(tagValue)` | `updateUserTag` → refresh getlist |
| `headleReceive` | `createAppCode` → 显示领卡 confirmModal |
| `headleForward` | `getShareKey` → navigateToMiniProgram 跳转会员端领卡页 |
| `headleSelf` | 显示文字弹窗 addconfirmModal |
| `handleCopy` | 复制文字到剪贴板 |
| `handleCancelbtn` | 关闭领卡弹窗 |
| `toggleCard(card)` | → `/pageMember/details/cardDetail?userCardId=N` |
| `moreProject(data, cardType)` | → `cardAllProject.open(data, cardType)` |
| `remarkCard(card)` | → `remarkAppointmentCard.open(cardRemark, cardId)` |
| `headleRecordList(records)` | recordList = records |
| `headleNewCard` | → `newcardRef.open(userId)` |
| `newCardSubmit(payload)` | `addUserCard` → getlist + getSumCardInfo + toast |
| `recycle` | → `/pageMember/del-card/del-card?dellist=JSON&title=姓名` |
| `rechargeAmount` | → `/pageMember/details/rechargeAmount?userId=...&...` |
| `courseDetail` | → `/pageMember/details/courseDetail?mode=0`（团课） |
| `personalDetail` | → `/pageMember/details/courseDetail?mode=1`（私教） |
| `truantDetail` | → `/pageMember/details/courseDetail?mode=2`（旷课） |
| `pointDetail` | → `/pageMember/details/memberPoint?userId=...` |
| `editRemark(text, appointId)` | `saveStaffRemark` |
| `editRemarkCard(text, cardId)` | `updateUserCardRemark` → getlist |
| `makeOverSubmit(result)` | 更新 local state（userPhone/userRealname/userFaceurl） |

---

## 3. 原版按钮路径对照

| 原版触发 | 原版路径 | 新版路径 | 现状 |
|---|---|---|---|
| `headleSetUp` | `/pageMember/information/index?userId=N` | `/pages/members/form?id=N` | 已存在 ✅ |
| `toggleCard` | `/pageMember/details/cardDetail?userCardId=N` | `/pages/members/card-detail?memberId=N&memberCardId=N` | 已存在 ✅ |
| `recycle` | `/pageMember/del-card/del-card` | `/pages/members/archived-cards/index` | 已存在 ✅ |
| `rechargeAmount` | `/pageMember/details/rechargeAmount` | `/pages/members/points?id=N`（积分+消费） | 部分（积分有，**总消费/剩余价值无**） |
| `courseDetail` | `/pageMember/details/courseDetail?mode=0` | 无独立页（新版在预约 Tab 内筛选团课） | 需新建或复用预约 Tab |
| `personalDetail` | `/pageMember/details/courseDetail?mode=1` | 无独立页 | 同上 |
| `truantDetail` | `/pageMember/details/courseDetail?mode=2` | 无独立页 | 同上 |
| `pointDetail` | `/pageMember/details/memberPoint` | `/pages/members/points?id=N` | ✅ |
| `headleNewCard` | 发卡弹窗 `newcardRef.open(userId)` | `/pages/members/issue-card?memberId=N` | ✅（改为独立页） |
| `headleReceive` | 领卡弹窗 | 无（新版无此功能） | 可选 |

---

## 4. 原版 data 字段 + 后端契约

| 字段 | 来源 API | 新版现状 |
|---|---|---|
| `personalTainerInfo` | `getUserCardInfo`（user/字段配置） | ✅ `fetchCrmMember` 已覆盖 id/name/mobile/gender/birth/owner/tags/stickyRemark |
| `cardlist` | `getUserCardInfo.cardlist` | ✅ `fetchMemberCards` → `StaffMemberCardSummary[]` |
| `userField` | `getUserCardInfo.userField`（自定义字段配置） | ❌ 新版无字段配置支持（CRUD 字段列表缺失） |
| `totalPayAmount` | `getSumCardInfo`（独立接口） | ❌ 新版无此指标 API |
| `noLogin` | `personalTainerInfo.noLogin` | ✅ `member.appAccessStatus` |
| `tagValue` | `personalTainerInfo.tagValue`（0=无，1~5=颜色） | ✅ `member.tags[].color` |
| `userFaceurl / userRealname / userNickname / userPhone` | `personalTainerInfo` | ✅ `member.avatarUrl / name / mobileMasked` |
| `userRemark`（会员备注） | `personalTainerInfo.userRemark`（sticky remark） | ✅ `member.stickyRemark` |
| `noClassDays`（未上课天数） | `personalTainerInfo.noClassDays` | ❌ 后端暂无字段（需加 computed 或 API） |
| `dellist`（删除的卡） | `getUserCardInfo.dellist` | ✅ `fetchMemberCards(archived=true)` |
| `unionid`（会员是否已关联账户） | `personalTainerInfo.unionid` | ✅ `member.accountLinked` |

---

## 5. 新版 vs 原版差异清单

### 视觉差异（最大）
| 项目 | 原版 | 新版 |
|---|---|---|
| 布局 | 顶部沉浸 banner（模糊头像背景） | 分段白底卡片 |
| 指标卡 | 横向滚动，8项 | 横向滚动，5项（缺总消费/已耗卡/剩余价值） |
| 会员卡 | member-card 组件（多彩渐变） | 简单文字行 |
| 标签 | 6色旗图标 | u-tag |
| 流失预警 | 30/60/90/120天图片 | 无 |

### 缺失功能（阻塞 UI 1:1 复刻）
1. **`totalPayAmount` 指标**：总消费/团课月累计/私教/积分/旷课/已耗卡/剩余价值 — 后端无独立接口，需新增或从现有数据计算
2. **`noClassDays` 流失预警**：后端无此字段（可从 appointment 推算或独立加字段）
3. **`userField` 自定义字段**：原版 `information_phone` 动态渲染（身高/体重/身份证/会籍顾问等），新版写死在 CRM 信息区
4. **会员卡 UI**：原版 `member-card` 组件多彩渐变卡面，新版是纯文字列表
5. **卡备注**：原版在卡列表下方直接显示 `remarkCard`，点击弹窗编辑；新版在 card-detail 页
6. **领卡弹窗**：原版有完整的二维码/转发/自领流程，新版无

### 新版优于原版的点（保留）
- 状态转换（lead→active/frozen）
- App access 屏蔽
- 标签管理（actionSheet）
- 内部备注（历史列表 + 新增）
- 积分明细独立页
- 归档卡独立页

---

## 6. 执行计划

分三步走，每步可独立验收：

### Phase A：UI 骨架对齐（视觉优先）
1. 将详情页改为**顶部沉浸式 banner**（背景头像模糊 + 半透明遮罩）
2. 导航栏固定 + scroll 渐变 opacity
3. 个人信息居中卡片（头像/姓名/标签/设置/更多按钮）
4. 横向滚动指标卡（8项占位，留空 API 缺口）
5. 经营指标区补齐颜色样式

### Phase B：缺失功能补全
1. 后端新增 `totalPayAmount`（从 ledger/card 数据计算）
2. 后端新增 `noClassDays`（从 appointment 推算）
3. 卡列表 UI 改用 member-card 组件（或彩色卡面）
4. userField 动态字段（简化版：先固定展示身高体重等）

### Phase C：弹窗/子页路径
1. 更多下拉菜单（屏蔽/取消屏蔽/转让账号）
2. 发卡弹窗改为独立页（issue-card）
3. 编辑资料 → form
4. 卡详情 → card-detail
5. 积分/消费/团课/私教/旷课详情 → points 或复用预约 Tab

---

执行 → 检查 → 提交。

---

## 7. 当前落地状态（2026-08-22）

- Phase B 的经营指标已改为详情接口服务端权威汇总：总消费、团课/私教、旷课、已耗卡、剩余价值、距上次上课天数。
- 指标分别受订单、预约历史、会员卡读取权限保护；无权限字段返回 `null`，前端展示占位符。
- 已移除依赖分页订单/预约列表的前端统计，避免历史数据超过分页上限后统计失真。
- PHP 语法/格式、OpenAPI 契约、员工端 TypeScript、微信小程序构建及相关 Feature 测试均通过；微信开发者工具视觉验收仍待授权连接。
