<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onReachBottom, onShow } from "@dcloudio/uni-app";
import { requireStaffAuth } from "@/auth/guard";
import { fetchCrmMembers, fetchMemberCards } from "@/api/crm";
import {
  batchAdjustMemberCardBalances,
  batchExtendMemberCardValidity,
  batchFreezeMemberCards,
  batchUnfreezeMemberCards,
} from "@/api/member-cards";
import { useSessionStore } from "@/stores/session";
import type { CrmMember, StaffMemberCardSummary } from "@/types/crm";

type BatchMode = "balance" | "validity" | "freeze" | "unfreeze";

const session = useSessionStore();
const loading = ref(true);
const loadingMore = ref(false);
const submitting = ref(false);
const members = ref<CrmMember[]>([]);
const selectedIds = ref<number[]>([]);
const page = ref(1);
const lastPage = ref(1);
const mode = ref<BatchMode>("balance");
const query = ref("");

const amount = ref("100");
const direction = ref<"credit" | "debit">("credit");
const validUntil = ref("");
const reason = ref("");

const canBalance = computed(() => session.can("member-card.balance.adjust"));
const canValidity = computed(() => session.can("member-card.validity.extend"));
const canFreeze = computed(() => session.can("member-card.freeze"));

const modeOptions = computed(() => [
  { value: "balance" as const, label: "调余额", enabled: canBalance.value },
  { value: "validity" as const, label: "延期", enabled: canValidity.value },
  { value: "freeze" as const, label: "停卡", enabled: canFreeze.value },
  { value: "unfreeze" as const, label: "复卡", enabled: canFreeze.value },
]);

const allSelected = computed(() => members.value.length > 0 && selectedIds.value.length === members.value.length);

function uuid() {
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

function toggleSelect(id: number) {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter((item) => item !== id);
  } else {
    selectedIds.value = [...selectedIds.value, id];
  }
}

function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value = [];
  } else {
    selectedIds.value = members.value.map((item) => item.id);
  }
}

async function load(reset = true) {
  if (!session.currentSiteId) return;
  if (reset) {
    page.value = 1;
    loading.value = true;
  } else {
    loadingMore.value = true;
  }
  try {
    const requestedPage = reset ? 1 : page.value + 1;
    const response = await fetchCrmMembers(session.currentSiteId, {
      page: requestedPage,
      perPage: 50,
      q: query.value.trim() || undefined,
      includeVisitors: false,
    });
    members.value = reset ? response.data.items : [...members.value, ...response.data.items];
    page.value = requestedPage;
    lastPage.value = response.data.pagination.lastPage;
    if (reset) selectedIds.value = [];
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "加载失败", icon: "none" });
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

async function resolveCards(memberIds: number[]): Promise<StaffMemberCardSummary[]> {
  if (!session.currentSiteId) return [];
  const siteId = session.currentSiteId;
  const cards: StaffMemberCardSummary[] = [];
  for (const memberId of memberIds) {
    try {
      const response = await fetchMemberCards(siteId, memberId);
      const list = response.data || [];
      const usable = list.filter((card) => {
        if (mode.value === "freeze") return card.status === "active" || card.status === "issued";
        if (mode.value === "unfreeze") return card.status === "frozen";
        return card.status !== "archived" && card.status !== "void";
      });
      if (usable[0]) cards.push(usable[0]);
    } catch {
      // skip member without readable cards
    }
  }
  return cards;
}

async function submit() {
  if (!session.currentSiteId || selectedIds.value.length === 0) {
    uni.showToast({ title: "请先勾选会员", icon: "none" });
    return;
  }
  const option = modeOptions.value.find((item) => item.value === mode.value);
  if (!option?.enabled) {
    uni.showToast({ title: "暂无该操作权限", icon: "none" });
    return;
  }
  if (mode.value === "balance") {
    const n = Number(amount.value);
    if (!Number.isFinite(n) || n <= 0) {
      uni.showToast({ title: "请输入有效金额", icon: "none" });
      return;
    }
  }
  if (mode.value === "validity" && !validUntil.value) {
    uni.showToast({ title: "请选择延期至日期", icon: "none" });
    return;
  }

  submitting.value = true;
  try {
    const cards = await resolveCards(selectedIds.value);
    if (!cards.length) {
      uni.showToast({ title: "所选会员没有可操作会员卡", icon: "none" });
      return;
    }
    const commandKey = uuid();
    let result;
    if (mode.value === "balance") {
      result = await batchAdjustMemberCardBalances(session.currentSiteId, {
        commandKey,
        items: cards.map((card) => ({
          memberCardId: card.id,
          commandKey: uuid(),
          direction: direction.value,
          amount: Number(amount.value),
          reason: reason.value || undefined,
        })),
      });
    } else if (mode.value === "validity") {
      result = await batchExtendMemberCardValidity(session.currentSiteId, {
        commandKey,
        items: cards.map((card) => ({
          memberCardId: card.id,
          commandKey: uuid(),
          validUntil: validUntil.value,
          reason: reason.value || undefined,
        })),
      });
    } else if (mode.value === "freeze") {
      result = await batchFreezeMemberCards(session.currentSiteId, {
        commandKey,
        items: cards.map((card) => ({
          memberCardId: card.id,
          commandKey: uuid(),
          reason: reason.value || undefined,
        })),
      });
    } else {
      result = await batchUnfreezeMemberCards(session.currentSiteId, {
        commandKey,
        items: cards.map((card) => ({
          memberCardId: card.id,
          commandKey: uuid(),
          reason: reason.value || undefined,
        })),
      });
    }
    const payload = result.data;
    const ok = payload?.succeeded?.length ?? 0;
    const fail = payload?.failed?.length ?? 0;
    uni.showModal({
      title: "批量结果",
      content: `成功 ${ok} 张卡，失败 ${fail} 张。每位会员默认取第一张可操作卡。`,
      showCancel: false,
    });
    selectedIds.value = [];
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "提交失败", icon: "none" });
  } finally {
    submitting.value = false;
  }
}

onLoad((options) => {
  const action = String(options?.action || "");
  if (action === "balance" || action === "validity" || action === "freeze" || action === "unfreeze") {
    mode.value = action;
  }
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 30);
  validUntil.value = tomorrow.toISOString().slice(0, 10);
});

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onReachBottom(() => {
  if (!loadingMore.value && page.value < lastPage.value) load(false);
});
</script>

<template>
  <u-loading-page :loading="loading || submitting" />
  <view class="page">
    <view class="mode-row">
      <view
        v-for="item in modeOptions"
        :key="item.value"
        class="mode-chip"
        :class="{ active: mode === item.value, disabled: !item.enabled }"
        @tap="item.enabled && (mode = item.value)"
      >
        {{ item.label }}
      </view>
    </view>

    <view class="toolbar">
      <view class="search-box">
        <input v-model="query" class="search-input" placeholder="搜索会员名/手机号" confirm-type="search" @confirm="load()" />
        <text class="search-btn" @tap="load()">搜索</text>
      </view>
      <view class="select-all" @tap="toggleSelectAll">{{ allSelected ? "取消全选" : "全选本页" }}</view>
    </view>

    <view class="hint">已选 {{ selectedIds.length }} 人；提交时对每人取第一张可操作会员卡。</view>

    <view v-for="member in members" :key="member.id" class="member-row" @tap="toggleSelect(member.id)">
      <view class="check" :class="{ on: selectedIds.includes(member.id) }" />
      <view class="meta">
        <text class="name">{{ member.name || "未命名" }}</text>
        <text class="sub">{{ member.mobileMasked || "未留手机号" }}</text>
      </view>
    </view>

    <view v-if="!loading && members.length === 0" class="empty">暂无会员</view>

    <view class="form-card">
      <template v-if="mode === 'balance'">
        <view class="field">
          <text class="label">方向</text>
          <view class="dir-row">
            <text class="dir" :class="{ on: direction === 'credit' }" @tap="direction = 'credit'">增加</text>
            <text class="dir" :class="{ on: direction === 'debit' }" @tap="direction = 'debit'">扣减</text>
          </view>
        </view>
        <view class="field">
          <text class="label">金额</text>
          <input v-model="amount" class="input" type="digit" placeholder="金额" />
        </view>
      </template>
      <template v-else-if="mode === 'validity'">
        <view class="field">
          <text class="label">延期至</text>
          <picker mode="date" :value="validUntil" @change="(e: any) => validUntil = e.detail.value">
            <view class="input">{{ validUntil || "选择日期" }}</view>
          </picker>
        </view>
      </template>
      <view class="field">
        <text class="label">原因</text>
        <input v-model="reason" class="input" placeholder="可选" />
      </view>
      <button class="submit" :disabled="submitting || selectedIds.length === 0" @click="submit">提交批量操作</button>
    </view>
  </view>
</template>

<style scoped lang="scss">
.page {
  min-height: 100vh;
  padding: 24rpx 24rpx 220rpx;
  background: #f5f5f5;
}

.mode-row {
  display: flex;
  gap: 12rpx;
  margin-bottom: 16rpx;
}

.mode-chip {
  padding: 12rpx 22rpx;
  background: #fff;
  border-radius: 999rpx;
  font-size: 24rpx;
  color: #666;
}

.mode-chip.active {
  background: #fbd128;
  color: #181818;
  font-weight: 600;
}

.mode-chip.disabled {
  opacity: 0.4;
}

.toolbar {
  display: flex;
  align-items: center;
  gap: 16rpx;
  margin-bottom: 12rpx;
}

.search-box {
  flex: 1;
  display: flex;
  align-items: center;
  background: #fff;
  border-radius: 35rpx;
  padding: 0 20rpx;
  height: 68rpx;
}

.search-input {
  flex: 1;
  font-size: 26rpx;
}

.search-btn {
  color: #ed920f;
  font-size: 24rpx;
}

.select-all {
  color: #003d82;
  font-size: 24rpx;
}

.hint {
  margin-bottom: 16rpx;
  color: #989898;
  font-size: 22rpx;
}

.member-row {
  display: flex;
  align-items: center;
  gap: 20rpx;
  padding: 24rpx;
  margin-bottom: 12rpx;
  background: #fff;
  border-radius: 16rpx;
}

.check {
  width: 36rpx;
  height: 36rpx;
  border: 2rpx solid #c8c9cc;
  border-radius: 50%;
  box-sizing: border-box;
}

.check.on {
  border-color: #fbd128;
  background: #fbd128;
}

.meta {
  flex: 1;
  min-width: 0;
}

.name {
  display: block;
  font-size: 28rpx;
  color: #181818;
}

.sub {
  display: block;
  margin-top: 6rpx;
  font-size: 22rpx;
  color: #989898;
}

.empty {
  padding: 80rpx 0;
  text-align: center;
  color: #bfbfbf;
}

.form-card {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 24rpx 28rpx calc(24rpx + env(safe-area-inset-bottom));
  background: #fff;
  box-shadow: 0 -8rpx 24rpx rgba(0, 0, 0, 0.06);
}

.field {
  display: flex;
  align-items: center;
  margin-bottom: 16rpx;
}

.label {
  width: 100rpx;
  font-size: 26rpx;
  color: #666;
}

.input {
  flex: 1;
  height: 64rpx;
  line-height: 64rpx;
  padding: 0 16rpx;
  background: #f5f5f5;
  border-radius: 12rpx;
  font-size: 26rpx;
}

.dir-row {
  display: flex;
  gap: 12rpx;
}

.dir {
  padding: 10rpx 24rpx;
  background: #f5f5f5;
  border-radius: 999rpx;
  font-size: 24rpx;
}

.dir.on {
  background: #fbd128;
  font-weight: 600;
}

.submit {
  margin-top: 8rpx;
  height: 80rpx;
  line-height: 80rpx;
  background: #fbd128;
  color: #181818;
  border-radius: 40rpx;
  font-size: 28rpx;
}

.submit::after {
  border: 0;
}
</style>
