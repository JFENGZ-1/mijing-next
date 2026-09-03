<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { requireStaffAuth } from "@/auth/guard";
import { fetchCrmMembers } from "@/api/crm";
import MemberCardSheet from "@/components/member-card-sheet/member-card-sheet.vue";
import FfBottomLogo from "@/components/ff-bottom-logo/ff-bottom-logo.vue";
import { useSessionStore } from "@/stores/session";
import type { CrmMember } from "@/types/crm";

const session = useSessionStore();
const keywords = ref("");
const loading = ref(false);
const members = ref<CrmMember[]>([]);
const total = ref(0);
const errorMessage = ref("");
const cardSheetShow = ref(false);
const cardSheetMemberId = ref<number | null>(null);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

const statusBarHeight = uni.getSystemInfoSync().statusBarHeight ?? 20;
const customBarHeight = (() => {
  try {
    const menu = uni.getMenuButtonBoundingClientRect();
    return menu.height + (menu.top - statusBarHeight) * 2;
  } catch {
    return 44;
  }
})();
const navTotalPx = statusBarHeight + customBarHeight;

function balanceText(member: CrmMember) {
  if (member.balanceAmount == null || !member.balanceUnit) return "";
  return `余${member.balanceAmount}${member.balanceUnit}`;
}

function tagColor(member: CrmMember) {
  return member.tags?.[0]?.color || "";
}

function highlightName(name: string) {
  const q = keywords.value.trim();
  if (!q || !name) return name;
  const idx = name.toLowerCase().indexOf(q.toLowerCase());
  if (idx < 0) return name;
  return `${name.slice(0, idx)}『${name.slice(idx, idx + q.length)}』${name.slice(idx + q.length)}`;
}

async function search() {
  const q = keywords.value.trim();
  if (!session.currentSiteId) {
    errorMessage.value = "当前账号没有可用场馆";
    return;
  }
  if (!q) {
    members.value = [];
    total.value = 0;
    errorMessage.value = "";
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await fetchCrmMembers(session.currentSiteId, {
      page: 1,
      perPage: 100,
      q,
      includeVisitors: true,
    });
    members.value = response.data.items.slice(0, 100);
    total.value = response.data.pagination.total;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "搜索失败";
    members.value = [];
    total.value = 0;
  } finally {
    loading.value = false;
  }
}

function onInput(e: { detail: { value: string } }) {
  keywords.value = e.detail.value;
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    void search();
  }, 320);
}

function openMember(member: CrmMember) {
  uni.navigateTo({ url: `/subpackages/members/detail?id=${member.id}` });
}

function openCards(member: CrmMember) {
  cardSheetMemberId.value = member.id;
  cardSheetShow.value = true;
}

function goBack() {
  uni.navigateBack();
}

onShow(async () => {
  await requireStaffAuth();
});
</script>

<template>
  <view class="search-page">
    <view class="fixed-box" :style="{ height: `${navTotalPx + 55}px` }">
      <view class="cu-status" :style="{ height: `${statusBarHeight}px` }" />
      <view class="cu-capsule" :style="{ height: `${customBarHeight}px` }">
        <view class="back" @tap="goBack">
          <u-icon name="arrow-left" size="18" color="#181818" />
        </view>
        <text class="title">搜索会员</text>
      </view>
      <view class="top-search-box">
        <view class="input-box">
          <u-icon name="search" size="36rpx" color="#989898" />
          <input
            class="search-input"
            type="text"
            :value="keywords"
            focus
            placeholder="会员名/手机号"
            placeholder-class="tips"
            confirm-type="search"
            @input="onInput"
            @confirm="search"
          />
        </view>
      </view>
    </view>

    <view class="placeholder" :style="{ height: `${navTotalPx + 55}px` }" />

    <view class="member">
      <view class="num">共找到 {{ total > 100 ? 100 : total }} 名</view>
      <view v-if="loading" class="loading-wrap">
        <u-loading-icon size="28" color="#ed920f" />
      </view>
      <u-alert v-else-if="errorMessage" type="error" :description="errorMessage" />
      <view v-else>
        <view v-for="member in members" :key="member.id" class="item">
          <view class="item_flex" @tap="openMember(member)">
            <view class="portrait-wrap">
              <image
                v-if="member.avatarUrl"
                class="portrait"
                :class="{ grey: member.appAccessStatus === 'blocked' }"
                :src="member.avatarUrl"
                mode="aspectFill"
              />
              <view
                v-else
                class="portrait portrait-fallback"
                :class="{ grey: member.appAccessStatus === 'blocked' }"
              >
                {{ (member.name || "?").slice(0, 1) }}
              </view>
              <view v-if="member.appAccessStatus === 'blocked'" class="forbidden">禁</view>
            </view>
            <view class="item_name">
              <view class="flag">
                <text class="name">{{ highlightName(member.name || "未命名会员") }}</text>
                <view v-if="member.hasStickyRemark" class="remark-dot" />
                <view v-if="tagColor(member)" class="flag-dot" :style="{ background: tagColor(member) }" />
              </view>
              <view class="item_time">
                <text class="time">{{ member.joinedAt?.slice(0, 10) || "—" }}</text>
                <text v-if="balanceText(member)">{{ balanceText(member) }}</text>
              </view>
              <view v-if="member.mobileMasked" class="telephone">手机号：{{ member.mobileMasked }}</view>
            </view>
          </view>
          <view class="card" @tap.stop="openCards(member)">
            <u-icon
              v-if="(member.cardCount || 0) === 0"
              name="minus-circle"
              size="42rpx"
              color="#d8d8d8"
            />
            <u-icon
              v-else-if="(member.cardCount || 0) === 1"
              name="integral-fill"
              size="42rpx"
              color="#ed920f"
            />
            <u-icon v-else name="grid-fill" size="42rpx" color="#ed920f" />
          </view>
        </view>
        <view v-if="total >= 100" class="much-warning">搜索结果过多，仅显示前100条</view>
      </view>
    </view>

    <ff-bottom-logo />
    <member-card-sheet v-model:show="cardSheetShow" :member-id="cardSheetMemberId" />
  </view>
</template>

<style scoped lang="scss">
.search-page {
  min-height: 100vh;
  background: #fff;
}

.fixed-box {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  z-index: 20;
  background: #fbd128;
}

.cu-capsule {
  display: flex;
  align-items: center;
  padding-left: 12rpx;
}

.back {
  padding: 12rpx;
}

.title {
  font-size: 34rpx;
  font-weight: 600;
  color: #181818;
}

.top-search-box {
  padding: 6rpx 28rpx 18rpx 35rpx;
}

.input-box {
  display: flex;
  align-items: center;
  height: 74rpx;
  padding-left: 22rpx;
  background: #fff;
  border-radius: 35rpx;
}

.search-input {
  flex: 1;
  margin-left: 18rpx;
  font-size: 26rpx;
  color: #181818;
}

.tips {
  color: #989898;
  font-size: 26rpx;
}

.member {
  padding: 20rpx 0 40rpx;
}

.num {
  padding: 10rpx 35rpx 20rpx;
  color: #989898;
  font-size: 22rpx;
}

.loading-wrap {
  padding: 80rpx;
  display: flex;
  justify-content: center;
}

.item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx 28rpx 24rpx 35rpx;
  border-bottom: 1rpx solid #f0f0f0;
}

.item_flex {
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 0;
}

.portrait-wrap {
  position: relative;
  margin-right: 20rpx;
  flex-shrink: 0;
}

.portrait {
  width: 84rpx;
  height: 84rpx;
  border-radius: 50%;
}

.portrait.grey {
  opacity: 0.45;
  filter: grayscale(1);
}

.portrait-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  background: #5fa3ea;
  color: #fff;
  font-size: 32rpx;
}

.forbidden {
  position: absolute;
  right: -2rpx;
  bottom: -2rpx;
  width: 28rpx;
  height: 28rpx;
  border-radius: 50%;
  background: #dc3c5c;
  color: #fff;
  font-size: 16rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.item_name {
  flex: 1;
  min-width: 0;
}

.flag {
  display: flex;
  align-items: center;
}

.name {
  color: #181818;
  font-size: 28rpx;
  margin-right: 8rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.remark-dot {
  width: 18rpx;
  height: 18rpx;
  border-radius: 4rpx;
  background: #fbd128;
  margin-right: 8rpx;
}

.flag-dot {
  width: 16rpx;
  height: 16rpx;
  border-radius: 50%;
}

.item_time {
  display: flex;
  gap: 24rpx;
  margin-top: 8rpx;
  color: #989898;
  font-size: 22rpx;
}

.telephone {
  margin-top: 6rpx;
  color: #989898;
  font-size: 22rpx;
}

.card {
  width: 72rpx;
  height: 72rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.much-warning {
  padding: 24rpx;
  text-align: center;
  color: #ed920f;
  font-size: 22rpx;
}
</style>
