<script setup lang="ts">
/** 对标原版 pageMember/details/rechargeAmount：累计消费明细 */
import { computed, ref } from "vue";
import { onLoad, onReachBottom, onShow } from "@dcloudio/uni-app";
import {
  appendMemberOrderInternalNote,
  correctMemberOrderAmount,
  fetchMemberOrders,
  type StaffMemberCardOrderItem,
  voidMemberOrder,
} from "@/api/member-cards";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import CustomNav from "@/components/custom-nav/custom-nav.vue";
import FfBottomLogo from "@/components/ff-bottom-logo/ff-bottom-logo.vue";
import { createCommandKey } from "@/utils/command-key";

interface DayGroup {
  day: string;
  items: StaffMemberCardOrderItem[];
}

const session = useSessionStore();
const memberId = ref(0);
const userName = ref("会员");
const userFaceurl = ref("");
const totalPayAmount = ref<string | number>("--");
const loading = ref(true);
const loadingMore = ref(false);
const errorMessage = ref("");
const items = ref<StaffMemberCardOrderItem[]>([]);
const page = ref(1);
const lastPage = ref(1);
const actingId = ref<number | null>(null);
const actionCommandKeys = new Map<string, string>();

const canRead = computed(() => session.can("order.read"));
const canCorrect = computed(() => session.can("order.amount.correct"));
const canVoid = computed(() => session.can("order.void"));
const canManage = computed(() => canRead.value || canCorrect.value || canVoid.value);

const dayGroups = computed<DayGroup[]>(() => {
  const map = new Map<string, StaffMemberCardOrderItem[]>();
  for (const item of items.value) {
    const day = (item.createdAt || "").slice(0, 10) || "未知日期";
    if (!map.has(day)) map.set(day, []);
    map.get(day)!.push(item);
  }
  return [...map.entries()].map(([day, list]) => ({ day, items: list }));
});

const computedTotal = computed(() => {
  if (totalPayAmount.value !== "--" && totalPayAmount.value !== "" && totalPayAmount.value != null) {
    return totalPayAmount.value;
  }
  const sum = items.value
    .filter((i) => i.status !== "voided")
    .reduce((acc, i) => acc + Number(i.effectiveAmount || 0), 0);
  return items.value.length ? sum.toFixed(2) : "--";
});

async function load(reset = true) {
  if (!session.currentSiteId || !memberId.value) {
    loading.value = false;
    return;
  }
  if (!canRead.value) {
    loading.value = false;
    items.value = [];
    return;
  }
  if (reset) {
    page.value = 1;
    loading.value = true;
    errorMessage.value = "";
  } else {
    loadingMore.value = true;
  }
  try {
    const requested = reset ? 1 : page.value + 1;
    const response = await fetchMemberOrders(session.currentSiteId, memberId.value, requested, 20);
    const payload = response.data;
    const nextItems = payload?.items ?? [];
    items.value = reset ? nextItems : [...items.value, ...nextItems];
    page.value = requested;
    lastPage.value = payload?.pagination?.lastPage ?? 1;
    if (reset && (totalPayAmount.value === "--" || totalPayAmount.value === "")) {
      const sum = nextItems
        .filter((i) => i.status !== "voided")
        .reduce((acc, i) => acc + Number(i.effectiveAmount || 0), 0);
      if (nextItems.length) totalPayAmount.value = sum.toFixed(2);
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "消费明细加载失败";
    if (reset) items.value = [];
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

function formatTime(value: string | null | undefined) {
  if (!value) return "--";
  return value.slice(11, 16) || value.slice(0, 16).replace("T", " ");
}

function statusLabel(status: string) {
  return ({ paid: "已支付", pending: "待支付", pending_payment: "待支付", voided: "已作废" } as Record<string, string>)[status] || status;
}

function actionCommandKey(action: string, orderId: number) {
  const key = `${action}:${orderId}`;
  let commandKey = actionCommandKeys.get(key);
  if (!commandKey) {
    commandKey = createCommandKey();
    actionCommandKeys.set(key, commandKey);
  }
  return { key, commandKey };
}

function promptText(title: string, placeholderText: string, content = "") {
  return new Promise<string | null>((resolve) => {
    uni.showModal({
      title,
      content,
      editable: true,
      placeholderText,
      confirmText: "确定",
      success: (result) => resolve(result.confirm ? String(result.content || "").trim() : null),
      fail: () => resolve(null),
    });
  });
}

async function correctAmount(row: StaffMemberCardOrderItem) {
  if (!session.currentSiteId || actingId.value === row.id) return;
  const amountText = await promptText("更正订单金额", "请输入更正后的金额", row.effectiveAmount);
  if (amountText === null) return;
  const amount = Number(amountText);
  if (!Number.isFinite(amount) || amount < 0) {
    uni.showToast({ title: "请输入有效金额", icon: "none" });
    return;
  }
  const reason = await promptText("填写更正原因", "请输入原因（必填）");
  if (!reason) return;

  const command = actionCommandKey("correct", row.id);
  actingId.value = row.id;
  try {
    await correctMemberOrderAmount(session.currentSiteId, row.id, {
      amount,
      reason,
      commandKey: command.commandKey,
    });
    actionCommandKeys.delete(command.key);
    uni.showToast({ title: "金额已更正", icon: "success" });
    await load();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "金额更正失败", icon: "none" });
  } finally {
    actingId.value = null;
  }
}

async function appendNote(row: StaffMemberCardOrderItem) {
  if (!session.currentSiteId || actingId.value === row.id) return;
  const body = await promptText("添加内部备注", "请输入内部备注");
  if (!body) return;

  const command = actionCommandKey("note", row.id);
  actingId.value = row.id;
  try {
    await appendMemberOrderInternalNote(session.currentSiteId, row.id, {
      body,
      commandKey: command.commandKey,
    });
    actionCommandKeys.delete(command.key);
    uni.showToast({ title: "备注已添加", icon: "success" });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "备注添加失败", icon: "none" });
  } finally {
    actingId.value = null;
  }
}

async function voidOrder(row: StaffMemberCardOrderItem) {
  if (!session.currentSiteId || actingId.value === row.id) return;
  const reason = await promptText("作废待支付订单", "请输入作废原因");
  if (!reason) return;

  const command = actionCommandKey("void", row.id);
  actingId.value = row.id;
  try {
    await voidMemberOrder(session.currentSiteId, row.id, {
      reason,
      commandKey: command.commandKey,
    });
    actionCommandKeys.delete(command.key);
    uni.showToast({ title: "订单已作废", icon: "success" });
    await load();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "订单作废失败", icon: "none" });
  } finally {
    actingId.value = null;
  }
}

function openOrderActions(row: StaffMemberCardOrderItem) {
  if (!canManage.value || actingId.value != null) return;
  const actions: Array<{ label: string; run: () => Promise<void> }> = [];
  if (canRead.value) actions.push({ label: "添加内部备注", run: () => appendNote(row) });
  if (canCorrect.value && row.status !== "voided") {
    actions.push({ label: "更正订单金额", run: () => correctAmount(row) });
  }
  if (canVoid.value && row.status === "pending_payment") {
    actions.push({ label: "作废待支付订单", run: () => voidOrder(row) });
  }
  if (!actions.length) {
    uni.showToast({ title: "当前订单没有可用操作", icon: "none" });
    return;
  }
  uni.showActionSheet({
    itemList: actions.map((item) => item.label),
    success: (result) => {
      const action = actions[result.tapIndex];
      if (action) void action.run();
    },
  });
}

onLoad((query) => {
  memberId.value = Number(query?.id ?? 0);
  userName.value = decodeURIComponent(String(query?.name ?? "会员"));
  userFaceurl.value = decodeURIComponent(String(query?.avatar ?? ""));
  const amount = query?.total;
  if (amount != null && amount !== "" && amount !== "null" && amount !== "undefined") {
    totalPayAmount.value = decodeURIComponent(String(amount));
  } else {
    totalPayAmount.value = "--";
  }
});

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onReachBottom(async () => {
  if (!canRead.value || loadingMore.value || page.value >= lastPage.value) return;
  await load(false);
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page">
    <CustomNav :text="userName" bg="#FFFFFF" :head-url="userFaceurl" />
    <view class="main">
      <view class="content">
        <view class="setting_wrap">
          <view class="top_data">
            <view class="month_income">
              <view class="month_num">{{ computedTotal }}</view>
              <view class="month_text">累计消费(元)</view>
            </view>
          </view>
        </view>

        <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
        <u-empty v-if="!canRead" mode="permission" text="暂无订单查看权限" />

        <view v-else class="list">
          <template v-if="dayGroups.length">
            <view v-for="group in dayGroups" :key="group.day" class="item">
              <view class="item_time">{{ group.day }}</view>
              <view v-for="row in group.items" :key="row.id" class="item_con" @tap="openOrderActions(row)">
                <view class="list_item">
                  <view class="right">
                    <view class="item_conter">
                      卡名：{{ row.productName || row.memberCard?.name || "会员卡" }}
                      <view class="item_below">
                        <view class="item_below_time">{{ formatTime(row.createdAt) }}</view>
                      </view>
                    </view>
                    <view class="item_price">
                      ￥{{ row.effectiveAmount }}
                      <view v-if="row.originalAmount !== row.effectiveAmount" class="item_original_price">
                        原价 ￥{{ row.originalAmount }}
                      </view>
                      <view class="item_price_status">{{ statusLabel(row.status) }}</view>
                    </view>
                  </view>
                  <u-icon v-if="canManage" name="arrow-right" size="14" color="#bfbfbf" />
                </view>
                <view v-if="row.orderNo" class="border">
                  <view class="remark">
                    <text class="remarkfont">单号：</text>
                    <text class="remarkcontent">{{ row.orderNo }}</text>
                  </view>
                </view>
              </view>
            </view>
            <u-loadmore :status="page >= lastPage ? 'nomore' : loadingMore ? 'loading' : 'loadmore'" />
          </template>
          <view v-else class="empty">
            <u-icon name="order" size="64" color="#dadada" />
            <text>暂无消费记录</text>
          </view>
        </view>
      </view>
      <FfBottomLogo />
    </view>
  </view>
</template>

<style scoped lang="scss">
.page { min-height: 100vh; background: #f5f5f5; }
.main { min-height: 100vh; padding-top: calc(var(--status-bar-height, 20px) + 44px); background: #f5f5f5; }
.content { min-height: 1230rpx; background: #fff; }
.setting_wrap { overflow: hidden; background: #f5f5f5; border-top-left-radius: 21rpx; border-top-right-radius: 21rpx; }
.top_data { margin-bottom: 19rpx; background: #fff; }
.month_income { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60rpx 0 50rpx; }
.month_num { color: #ed920f; font-size: 66rpx; font-weight: 500; line-height: 100rpx; }
.month_text { color: #989898; font-size: 22rpx; }
.list { min-height: 950rpx; margin: 0 14rpx; background: #fff; border-radius: 21rpx; padding-bottom: 40rpx; }
.item_time { color: #181818; font-size: 35rpx; padding: 45rpx 0 0 15rpx; }
.list_item { display: flex; padding: 25rpx 16rpx 0 21rpx; }
.right { display: flex; flex: 1; justify-content: space-between; }
.item_conter { color: #181818; font-size: 28rpx; }
.item_below { display: flex; align-items: center; margin-top: 10rpx; }
.item_below_time { color: #989898; font-size: 22rpx; }
.item_price { color: #ed920f; font-size: 30rpx; font-weight: 500; text-align: right; }
.item_price_status { margin-top: 8rpx; color: #989898; font-size: 22rpx; font-weight: 400; }
.item_original_price { margin-top: 4rpx; color: #bfbfbf; font-size: 20rpx; font-weight: 400; text-decoration: line-through; }
.border { padding: 12rpx 21rpx 8rpx; }
.remark { display: flex; color: #989898; font-size: 22rpx; }
.remarkfont { flex-shrink: 0; }
.empty { display: flex; flex-direction: column; align-items: center; gap: 20rpx; padding: 120rpx 0; color: #bfbfbf; font-size: 26rpx; }
</style>
