<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { ApiError } from "@mijing/api-client";
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

const myRank = computed(() => ranking.value?.myRank ?? null);

function rankAvatarText(name: string | null) {
  return name?.trim().slice(0, 1) || "?";
}

function medalStyle(rank: number) {
  if (rank === 1) return "background:#f5a623;";
  if (rank === 2) return "background:#c0c0c0;";
  if (rank === 3) return "background:#cd7f32;";
  return "";
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
  // 仅首次显示全屏加载，返回本页时静默刷新
  loading.value = !ranking.value;
  errorMessage.value = "";
  disabled.value = false;
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
  <view v-if="!loading" class="ranking-page" :class="{ 'ranking-page--refreshing': listRefreshing }">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

    <u-empty v-if="disabled" mode="list" text="本场馆未开启月度排行" />

    <template v-else-if="ranking">
      <view class="bg-wrap">
        <view class="tips">
          <u-icon name="info-circle" size="14" color="#fff" />
          <text>每日凌晨更新排行</text>
        </view>
      </view>

      <view class="self-info">
        <view class="wrap">
          <view class="photo-image">
            <image v-if="myRank?.avatarUrl" class="avatar-img" :src="myRank.avatarUrl" mode="aspectFill" />
            <view v-else class="avatar-text">{{ rankAvatarText(myRank?.displayName ?? null) }}</view>
          </view>
          <view class="text-info">
            <view class="name">{{ myRank?.displayName || "会员" }}</view>
            <view class="ranking-info">
              <view class="item">
                排名
                <text v-if="myRank?.rank">{{ myRank.rank }}</text>
                <text v-else>未进前15名</text>
              </view>
              <view class="item" v-if="myRank">
                上课<text>{{ myRank.appointmentCount }}</text>次
              </view>
            </view>
          </view>
        </view>
      </view>

      <view class="controls">
        <view class="control-row">
          <view
            v-for="year in yearOptions"
            :key="year"
            class="pill"
            :class="{ active: selectedYear === year }"
            @tap="selectYear(year)"
          >
            {{ year }}
          </view>
          <view class="month-grid">
            <view
              v-for="month in monthOptions"
              :key="month"
              class="month-pill"
              :class="{ active: selectedMonth === month }"
              @tap="selectMonth(month)"
            >
              {{ month }}
            </view>
          </view>
        </view>
        <view class="opt-in">
          <text>参与排行</text>
          <u-switch :model-value="rankingOptIn" :disabled="savingOptIn" @change="toggleOptIn" />
        </view>
      </view>

      <u-empty v-if="ranking.items.length === 0" mode="list" text="本月暂无排行数据" />
      <view v-else class="list">
        <view v-for="item in ranking.items" :key="item.memberId" class="list-item">
          <view class="index" :class="{ text_white: item.rank <= 3, text_gary: item.rank > 3 }">
            <view v-if="item.rank <= 3" class="medal" :style="medalStyle(item.rank)" />
            <text>{{ item.rank }}</text>
          </view>
          <view class="photo">
            <image v-if="item.avatarUrl" class="avatar-img" :src="item.avatarUrl" mode="aspectFill" />
            <view v-else class="avatar-text">{{ rankAvatarText(item.displayName) }}</view>
          </view>
          <view class="name" :class="{ self: item.isMe }">{{ item.displayName || "会员" }}</view>
          <view class="count" :class="{ self: item.isMe }">{{ item.appointmentCount }}次</view>
          <view v-if="item.isMe" class="is-self">我</view>
        </view>
      </view>

      <bottom-logo />
    </template>
  </view>
</template>

<style scoped lang="scss">
.ranking-page {
  min-height: 100vh;
  background: #fff;
}

.ranking-page--refreshing {
  opacity: 0.72;
  transition: opacity 0.15s ease;
}

.bg-wrap {
  position: relative;
  height: 333rpx;
  width: 100%;
  background: linear-gradient(160deg, #f7d29f 0%, #e8b863 100%);
}

.bg-wrap .tips {
  position: absolute;
  top: 20rpx;
  right: 24rpx;
  display: flex;
  align-items: center;
  gap: 5rpx;
  color: #fff;
  font-size: 22rpx;
}

.self-info {
  display: flex;
  align-items: center;
  height: 183rpx;
  width: 681rpx;
  margin: -160rpx auto 0;
  padding: 0 20rpx;
  background: linear-gradient(-57deg, #fef7e7, #f1e4c4);
  border: 1rpx solid #fef7e7;
  border-radius: 19rpx;
  box-shadow: 0 8rpx 21rpx 0 hsla(0, 0%, 84%, 0.12);
  box-sizing: border-box;
}

.self-info .wrap {
  display: flex;
  align-items: center;
  width: 100%;
}

.photo-image {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 104rpx;
  height: 104rpx;
  margin-left: 12rpx;
  border-radius: 50%;
  overflow: hidden;
  background: $color-primary;
}

.avatar-text {
  color: #fff;
  font-size: 40rpx;
  font-weight: 500;
}

.avatar-img {
  display: block;
  width: 100%;
  height: 100%;
}

.text-info {
  margin-left: 24rpx;
  flex: 1;
}

.text-info .name {
  margin-bottom: 18rpx;
  color: #181818;
  font-size: 35rpx;
  font-weight: 700;
  line-height: 35rpx;
}

.ranking-info {
  display: flex;
  justify-content: space-between;
  color: #92722a;
  font-size: 26rpx;
}

.ranking-info .item {
  display: flex;
  align-items: baseline;
}

.ranking-info .item text {
  margin-left: 10rpx;
  font-size: 38rpx;
  line-height: 30rpx;
}

.controls {
  padding: 24rpx 28rpx 0;
}

.control-row {
  display: flex;
  flex-wrap: wrap;
  gap: 12rpx;
  align-items: center;
}

.pill {
  padding: 6rpx 20rpx;
  background: #f5f5f5;
  border-radius: 24rpx;
  color: $color-text-secondary;
  font-size: 22rpx;

  &.active {
    background: $color-primary;
    color: #fff;
  }
}

.month-grid {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 8rpx;
  width: 100%;
  margin-top: 12rpx;
}

.month-pill {
  text-align: center;
  padding: 4rpx 0;
  background: #f5f5f5;
  border-radius: 8rpx;
  color: $color-text-secondary;
  font-size: 20rpx;

  &.active {
    background: $color-primary;
    color: #fff;
  }
}

.opt-in {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 20rpx;
  padding: 16rpx 20rpx;
  background: #faf5f8;
  border-radius: 12rpx;
  color: $color-text;
  font-size: 26rpx;
}

.list {
  padding: 40rpx 50rpx 40rpx 25rpx;
}

.list-item {
  display: flex;
  align-items: center;
  margin-bottom: 40rpx;

  &:last-of-type {
    margin-bottom: 0;
  }
}

.list-item .self {
  color: #dc3c5c !important;
}

.list-item .index {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 42rpx;
  height: 42rpx;
}

.list-item .index text {
  position: relative;
  font-size: 24rpx;
}

.list-item .index.text_white {
  color: #fff;
}

.list-item .index.text_gary {
  color: #7e7e7e;
}

.medal {
  position: absolute;
  inset: 0;
  border-radius: 50%;
}

.list-item .photo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 84rpx;
  height: 84rpx;
  margin: 0 15rpx 0 25rpx;
  border-radius: 50%;
  overflow: hidden;
  background: #e8e8e8;
}

.list-item .photo .avatar-text {
  color: #fff;
  font-size: 32rpx;
}

.list-item .name {
  color: #181818;
  font-size: 26rpx;
}

.list-item .count {
  flex: 1;
  color: #7e7e7e;
  font-size: 26rpx;
  text-align: right;
}

.is-self {
  margin-left: 15rpx;
  color: #dc3c5c;
  font-size: 20rpx;
}
</style>
