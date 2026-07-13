<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { ApiError } from "@songguo/api-client";
import { getMemberMonthlyRanking, patchMemberRankingOptIn } from "@/api/member";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberMonthlyRanking } from "@/types/member";

const listRefreshing = ref(false);
const savingOptIn = ref(false);
const errorMessage = ref("");
const disabled = ref(false);
const ranking = ref<MemberMonthlyRanking | null>(null);
const rankingOptIn = ref(false);
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth() + 1);

const loading = ref(true);

const yearOptions = computed(() => {
  const current = new Date().getFullYear();
  return [current - 1, current, current + 1];
});

const monthOptions = Array.from({ length: 12 }, (_, index) => index + 1);

const myRankLabel = computed(() => {
  if (!ranking.value) return "";
  const { myRank } = ranking.value;
  if (myRank.rank != null) {
    return `我的排名：第 ${myRank.rank} 名 · 上课 ${myRank.appointmentCount} 次`;
  }
  if (!rankingOptIn.value) {
    return `本月上课 ${myRank.appointmentCount} 次 · 未参与排行`;
  }
  return `本月上课 ${myRank.appointmentCount} 次 · 暂未上榜`;
});

function rankAvatarText(name: string | null) {
  return name?.trim().slice(0, 1) || "?";
}

function syncOptInFromRanking(data: MemberMonthlyRanking) {
  rankingOptIn.value = data.viewerOptIn;
}

async function loadRanking() {
  const tenant = await ensureMemberTenant();
  if (!tenant) {
    errorMessage.value = "请先选择场馆";
    return;
  }

  const response = await getMemberMonthlyRanking(tenant.tenantId, selectedYear.value, selectedMonth.value);
  ranking.value = response.data;
  syncOptInFromRanking(response.data);
}

async function load() {
  loading.value = true;
  errorMessage.value = "";
  disabled.value = false;
  ranking.value = null;

  try {
    await loadRanking();
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 404) {
      disabled.value = true;
      return;
    }
    errorMessage.value = error instanceof Error ? error.message : "排行榜加载失败";
  } finally {
    loading.value = false;
  }
}

async function reloadRankingSoft() {
  listRefreshing.value = true;
  errorMessage.value = "";
  try {
    await loadRanking();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "排行榜加载失败";
  } finally {
    listRefreshing.value = false;
  }
}

async function selectYear(year: number) {
  if (selectedYear.value === year) return;
  selectedYear.value = year;
  await reloadRankingSoft();
}

async function selectMonth(month: number) {
  if (selectedMonth.value === month) return;
  selectedMonth.value = month;
  await reloadRankingSoft();
}

async function toggleOptIn() {
  const tenant = await ensureMemberTenant();
  if (!tenant) return;

  const next = !rankingOptIn.value;
  const previous = rankingOptIn.value;
  rankingOptIn.value = next;
  savingOptIn.value = true;
  errorMessage.value = "";

  try {
    const response = await patchMemberRankingOptIn(tenant.tenantId, next);
    rankingOptIn.value = response.data.rankingOptIn;
    await loadRanking();
    uni.showToast({ title: next ? "已参与排行" : "已退出排行", icon: "none" });
  } catch (error) {
    rankingOptIn.value = previous;
    errorMessage.value = error instanceof Error ? error.message : "设置失败";
  } finally {
    savingOptIn.value = false;
  }
}

onShow(async () => { if (await requireMemberAuth()) await load(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container" :class="{ 'page-container--refreshing': listRefreshing }">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <u-empty v-if="disabled" mode="list" text="本场馆未开启月度排行" />

    <template v-else-if="ranking">
      <view class="section-title">选择月份</view>
      <view class="picker-strip">
        <u-button
          v-for="year in yearOptions"
          :key="year"
          size="small"
          type="primary"
          :plain="selectedYear !== year"
          @click="selectYear(year)"
        >
          {{ year }}年
        </u-button>
      </view>
      <view class="picker-strip month-strip">
        <u-button
          v-for="month in monthOptions"
          :key="month"
          size="mini"
          type="primary"
          :plain="selectedMonth !== month"
          @click="selectMonth(month)"
        >
          {{ month }}
        </u-button>
      </view>

      <view class="opt-in-card">
        <view>
          <view class="opt-in-title">参与月度排行</view>
          <view class="opt-in-hint">开启后将在排行榜中展示您的上课次数</view>
        </view>
        <u-switch :model-value="rankingOptIn" :disabled="savingOptIn" @change="toggleOptIn" />
      </view>

      <view class="summary-card">
        <view class="summary-total">{{ selectedYear }}年{{ selectedMonth }}月排行</view>
        <view class="summary-meta">{{ myRankLabel }}</view>
      </view>

      <u-empty v-if="ranking.items.length === 0" mode="list" text="本月暂无排行数据" />
      <view v-for="item in ranking.items" :key="item.memberId" class="rank-item" :class="{ me: item.isMe }">
        <view class="rank-position">{{ item.rank }}</view>
        <u-avatar :src="item.avatarUrl || undefined" :text="rankAvatarText(item.displayName)" size="40" />
        <view class="rank-body">
          <view class="rank-name">
            {{ item.displayName || "会员" }}
            <text v-if="item.isMe" class="rank-me-tag">我</text>
          </view>
          <view class="rank-meta">上课 {{ item.appointmentCount }} 次</view>
        </view>
      </view>

      <view v-if="ranking.items.length > 0" class="list-footer">仅列出前 15 名会员</view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container--refreshing {
  opacity: 0.72;
  transition: opacity 0.15s ease;
}

.picker-strip {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-sm;
  margin-bottom: $spacing-md;
}

.month-strip {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
}

.opt-in-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: $spacing-md;
  margin-bottom: $spacing-md;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.opt-in-title {
  font-size: 30rpx;
  font-weight: 600;
}

.opt-in-hint {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.summary-card {
  margin-bottom: $spacing-md;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.summary-total {
  font-size: 32rpx;
  font-weight: 600;
}

.summary-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.rank-item {
  display: flex;
  align-items: center;
  gap: $spacing-sm;
  margin-bottom: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;

  &.me {
    border-color: $color-primary;
    background: rgba($color-primary, 0.06);
  }
}

.rank-position {
  width: 48rpx;
  text-align: center;
  font-size: 32rpx;
  font-weight: 600;
  color: $color-primary;
}

.rank-body {
  flex: 1;
  min-width: 0;
}

.rank-name {
  font-size: 30rpx;
  font-weight: 600;
}

.rank-me-tag {
  margin-left: 8rpx;
  padding: 2rpx 10rpx;
  color: $color-primary;
  font-size: 20rpx;
  font-weight: 500;
  border: 1rpx solid $color-primary;
  border-radius: 999rpx;
}

.rank-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.list-footer {
  margin-top: $spacing-md;
  text-align: center;
  color: $color-text-secondary;
  font-size: 24rpx;
}
</style>
