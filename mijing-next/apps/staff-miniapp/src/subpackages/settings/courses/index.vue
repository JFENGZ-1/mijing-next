<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchPrivateCoaches, fetchStaffCourseCatalog } from "@/api/catalog";
import type { CoachBookingWindow, CoachPrivateProfile } from "@/api/catalog";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CourseCatalogItem } from "@/types/catalog";
import type { CourseType } from "@/types/scheduling";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const activeTab = ref<CourseType>("group");
const items = ref<CourseCatalogItem[]>([]);
const coachProfiles = ref<CoachPrivateProfile[]>([]);
const total = ref(0);
const searchQuery = ref("");

const canRead = computed(() => session.can("course-catalog.read"));
const canWrite = computed(() => session.can("course-catalog.write"));
// 原版：团课 / 私教 两个大字 tab
const tabs = [
  { key: "group" as const, name: "团课" },
  { key: "private" as const, name: "私教课" },
];
const activeTabIndex = computed(() => tabs.findIndex((tab) => tab.key === activeTab.value));
const isPrivateTab = computed(() => activeTab.value === "private");

function difficultyStars(course: CourseCatalogItem) {
  const count = Math.min(5, Math.max(0, course.difficulty ?? 0));
  return "★".repeat(count);
}

// 原版 subject-card：开课规则文案（不限制 / 限X人 / 满X人开课）
function openRuleText(course: CourseCatalogItem) {
  const parts: string[] = [];
  if (course.maxCapacity != null) parts.push(`限${course.maxCapacity}人`);
  if (course.minCapacity != null && course.minCapacity > 0) parts.push(`满${course.minCapacity}人开课`);
  return parts.length ? parts.join("，") : "不限制";
}

// 私教 tab：显示私教教练档案（预约时间制，对标原版 drainer 列表）
const WEEK_NAMES = ["", "周一", "周二", "周三", "周四", "周五", "周六", "周日"];

function windowLabel(window: CoachBookingWindow) {
  const days = [...window.days].sort((a, b) => a - b);
  if (!days.length) return "";
  const continuous = days.length > 2 && days[days.length - 1] - days[0] === days.length - 1;
  const week = days.length === 7
    ? "周一至周日"
    : continuous
      ? `${WEEK_NAMES[days[0]]}至${WEEK_NAMES[days[days.length - 1]]}`
      : days.map((day) => WEEK_NAMES[day]).join("、");
  return `${week} ${window.start}~${window.end}`;
}

function profileSubjectText(profile: CoachPrivateProfile) {
  if (profile.subjectMode === "per_course") {
    return profile.courses.length ? `${profile.courses.length}个私教课目` : "待添加课目";
  }
  return `统一时长 ${profile.uniformDurationMinutes}分钟`;
}

const filteredProfiles = computed(() => {
  const keyword = searchQuery.value.trim();
  if (!keyword) return coachProfiles.value;
  return coachProfiles.value.filter((profile) => (profile.coachName || "").includes(keyword));
});

async function load() {
  if (!session.currentSiteId || !canRead.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    if (isPrivateTab.value) {
      coachProfiles.value = await fetchPrivateCoaches(session.currentSiteId);
      total.value = coachProfiles.value.length;
    } else {
      const response = await fetchStaffCourseCatalog(
        session.currentSiteId,
        1,
        50,
        searchQuery.value.trim() || undefined,
        activeTab.value,
      );
      items.value = response.items;
      total.value = response.pagination.total;
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "课程列表加载失败";
  } finally {
    loading.value = false;
  }
}

function switchTab(index: number) {
  const nextTab = tabs[index]?.key;
  if (!nextTab || nextTab === activeTab.value) return;
  activeTab.value = nextTab;
  load();
}

function openCreate() {
  if (!canWrite.value) {
    uni.showToast({ title: "暂无课程编辑权限", icon: "none" });
    return;
  }
  // 私教=预约时间制（教练档案），不走课程编辑
  if (isPrivateTab.value) {
    uni.navigateTo({ url: "/subpackages/settings/courses/private-edit" });
    return;
  }
  uni.navigateTo({ url: "/subpackages/settings/courses/edit?type=group" });
}

function openEdit(item: CourseCatalogItem) {
  if (!canWrite.value) return;
  uni.navigateTo({ url: `/subpackages/settings/courses/edit?id=${item.id}` });
}

function openProfileEdit(profile: CoachPrivateProfile) {
  if (!canWrite.value) return;
  uni.navigateTo({ url: `/subpackages/settings/courses/private-edit?id=${profile.id}` });
}

function onSearch() {
  load();
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(async () => {
  await load();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-shell"><view class="subject-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!canRead" mode="permission" text="暂无查看课程库权限" />

    <template v-else>
      <!-- 团课/私教 tabs（原版 34 号大字） -->
      <view class="head-tabs">
        <view
          v-for="(tab, index) in tabs"
          :key="tab.key"
          class="head-tab"
          :class="{ active: activeTabIndex === index }"
          @tap="switchTab(index)"
        >
          {{ tab.name }}
        </view>
      </view>

      <u-search
        v-model="searchQuery"
        placeholder="课程名称"
        search-icon-color="#FBD128"
        :show-action="false"
        @search="onSearch"
        @clear="onSearch"
      />

      <view class="num-line">{{ isPrivateTab ? `共${total}位私教教练` : `共${total}个课目` }}</view>

      <!-- 私教 tab：教练档案列表（预约时间制） -->
      <template v-if="isPrivateTab">
        <view v-for="profile in filteredProfiles" :key="profile.id" class="subject-card" @tap="openProfileEdit(profile)">
          <view class="coach-avatar">{{ (profile.coachName || "教")[0] }}</view>
          <view class="subject-main">
            <view class="subject-name-row">
              <text class="subject-name">{{ profile.coachName }}</text>
              <text v-if="profile.tagText" class="coach-tag">{{ profile.tagText }}</text>
            </view>
            <view class="subject-meta-row">
              <text v-if="profile.bookingWindows.length" class="subject-meta">{{ windowLabel(profile.bookingWindows[0]) }}</text>
              <text class="subject-meta"> · {{ profileSubjectText(profile) }}</text>
            </view>
          </view>
          <u-icon name="arrow-right" size="16" color="#bfbfbf" />
        </view>
        <u-empty v-if="filteredProfiles.length === 0" mode="list" text="还没有私教教练，点击右下角创建" />
      </template>

      <!-- 团课 tab：课目卡（原版 subject-card：名称/时长/难度/开课规则） -->
      <template v-else>
        <view v-for="item in items" :key="item.id" class="subject-card" @tap="openEdit(item)">
          <view class="subject-main">
            <view class="subject-name-row">
              <text class="subject-name">{{ item.name }}</text>
              <text v-if="difficultyStars(item)" class="subject-stars">{{ difficultyStars(item) }}</text>
            </view>
            <view class="subject-meta-row">
              <text class="subject-meta">{{ item.durationMinutes }}分钟</text>
              <text class="subject-meta"> · 开课规则：{{ openRuleText(item) }}</text>
              <text v-if="item.defaultRoomName" class="subject-meta"> · {{ item.defaultRoomName }}</text>
            </view>
          </view>
          <u-icon name="arrow-right" size="16" color="#bfbfbf" />
        </view>
        <u-empty v-if="items.length === 0" mode="list" text="还没有团课课目" />
      </template>

      <!-- 浮动创建按钮（对标原版 create-Employee 黄色圆钮） -->
      <view v-if="canWrite" class="create-fab" @tap="openCreate">
        <text class="fab-line1">创 建</text>
        <text class="fab-line2">{{ isPrivateTab ? "私教课" : "团课" }}</text>
      </view>
    </template>
  </view>
  </view>
</template>

<style scoped lang="scss">
// 黄色导航下的白色圆角主体（原版 subject 布局）
.page-shell {
  min-height: 100vh;
  background: $color-brand-yellow;
}

.subject-page {
  min-height: 100vh;
  padding: 30rpx $spacing-md 60rpx;
  background: #fff;
  border-radius: 24rpx 24rpx 0 0;
  box-sizing: border-box;
}

// —— 团课/私教大字 tabs（原版 fontSize 34） ——
.head-tabs {
  display: flex;
  gap: 48rpx;
  margin-bottom: $spacing-md;
}

.head-tab {
  position: relative;
  padding-bottom: 14rpx;
  color: #7e7e7e;
  font-size: 34rpx;

  &.active {
    color: $color-text;
    font-weight: 600;

    &::after {
      content: "";
      position: absolute;
      right: 20%;
      bottom: 0;
      left: 20%;
      height: 6rpx;
      border-radius: 3rpx;
      background: $color-brand-yellow;
    }
  }
}

.num-line {
  margin: 20rpx 4rpx 12rpx;
  color: $color-text-tertiary;
  font-size: 24rpx;
}

// —— 课目卡（原版 subject-card） ——
.subject-card {
  display: flex;
  align-items: center;
  gap: 16rpx;
  margin-bottom: 16rpx;
  padding: 28rpx 24rpx;
  background: $color-surface;
  border-radius: 16rpx;
}

.subject-main {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.subject-name-row {
  display: flex;
  align-items: center;
  gap: 14rpx;
}

.subject-name {
  overflow: hidden;
  font-size: 30rpx;
  font-weight: 600;
  color: $color-text;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.subject-stars {
  flex-shrink: 0;
  color: $color-brand-yellow;
  font-size: 24rpx;
  letter-spacing: 2rpx;
}

.subject-meta-row {
  margin-top: 10rpx;
}

.subject-meta {
  color: $color-text-secondary;
  font-size: 24rpx;
}

// —— 浮动创建按钮（原版黄色圆钮两行字） ——
.coach-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 88rpx;
  height: 88rpx;
  margin-right: 20rpx;
  background: #f5f5f5;
  border-radius: 18rpx;
  color: #505050;
  font-size: 32rpx;
}

.coach-tag {
  flex-shrink: 0;
  padding: 4rpx 14rpx;
  background: rgba(251, 209, 40, 0.18);
  border-radius: 999rpx;
  color: #d9a400;
  font-size: 20rpx;
}

.create-fab {
  position: fixed;
  right: 30rpx;
  bottom: 120rpx;
  z-index: 10;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 130rpx;
  height: 130rpx;
  border-radius: 50%;
  background: $color-brand-yellow;
  box-shadow: 0 6rpx 20rpx rgba(251, 209, 40, 0.5);
}

.fab-line1 {
  color: $color-text;
  font-size: 28rpx;
  font-weight: 600;
}

.fab-line2 {
  margin-top: 4rpx;
  color: $color-text;
  font-size: 22rpx;
}
</style>
