<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchStaffCourseCatalog } from "@/api/catalog";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CourseCatalogItem } from "@/types/catalog";
import type { CourseType } from "@/types/scheduling";

type CourseTab = "all" | CourseType;

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const activeTab = ref<CourseTab>("all");
const items = ref<CourseCatalogItem[]>([]);
const total = ref(0);
const searchQuery = ref("");

const canRead = computed(() => session.can("course-catalog.read"));
const canWrite = computed(() => session.can("course-catalog.write"));
const tabs = [
  { key: "all" as const, name: "全部" },
  { key: "group" as const, name: "团课" },
  { key: "private" as const, name: "私教" },
];
const activeTabIndex = computed(() => tabs.findIndex((tab) => tab.key === activeTab.value));

function courseTypeLabel(courseType: CourseType) {
  return courseType === "private" ? "私教" : "团课";
}

function courseSummary(course: CourseCatalogItem) {
  const parts = [`${course.durationMinutes} 分钟`, courseTypeLabel(course.courseType)];
  if (course.courseType === "group" && course.maxCapacity != null) {
    parts.push(`最多 ${course.maxCapacity} 人`);
  }
  if (course.courseType === "private" && course.coachName) {
    parts.push(course.coachName);
  }
  if (course.defaultRoomName) parts.push(course.defaultRoomName);
  return parts.join(" · ");
}

async function load() {
  if (!session.currentSiteId || !canRead.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await fetchStaffCourseCatalog(
      session.currentSiteId,
      1,
      50,
      searchQuery.value.trim() || undefined,
      activeTab.value === "all" ? undefined : activeTab.value,
    );
    items.value = response.items;
    total.value = response.pagination.total;
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
  uni.navigateTo({ url: "/pages/settings/courses/edit" });
}

function openEdit(item: CourseCatalogItem) {
  if (!canWrite.value) return;
  uni.navigateTo({ url: `/pages/settings/courses/edit?id=${item.id}` });
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
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!canRead" mode="permission" text="暂无查看课程库权限" />

    <template v-else>
      <u-tabs
        :list="tabs.map((tab) => ({ name: tab.name }))"
        :current="activeTabIndex"
        @change="switchTab"
      />

      <view class="summary-row">共 {{ total }} 个课程模板</view>

      <view class="search-row">
        <u-input
          v-model="searchQuery"
          placeholder="搜索课程名称"
          clearable
          @confirm="onSearch"
          @clear="onSearch"
        />
        <u-button text="搜索" size="small" @click="onSearch" />
      </view>

      <view v-if="canWrite" class="toolbar">
        <u-button type="primary" text="新建课程" @click="openCreate" />
      </view>

      <view
        v-for="item in items"
        :key="item.id"
        class="course-card"
        :class="{ clickable: canWrite }"
        @click="openEdit(item)"
      >
        <view class="course-main">
          <text class="course-name">{{ item.name }}</text>
          <text class="course-meta">{{ courseSummary(item) }}</text>
        </view>
        <u-tag :text="courseTypeLabel(item.courseType)" size="mini" />
      </view>

      <u-empty v-if="items.length === 0" mode="list" text="暂无课程模板" />
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  padding: 24rpx;
}

.summary-row {
  margin: 16rpx 0;
  color: #5f6368;
  font-size: 26rpx;
}

.search-row {
  display: flex;
  gap: 16rpx;
  align-items: center;
  margin-bottom: 24rpx;
}

.toolbar {
  margin-bottom: 24rpx;
}

.course-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24rpx;
  margin-bottom: 16rpx;
  border-radius: 16rpx;
  background: #fff;
}

.course-card.clickable:active {
  opacity: 0.85;
}

.course-main {
  flex: 1;
  min-width: 0;
  margin-right: 16rpx;
}

.course-name {
  display: block;
  font-size: 30rpx;
  font-weight: 600;
  color: #202124;
}

.course-meta {
  display: block;
  margin-top: 8rpx;
  color: #5f6368;
  font-size: 24rpx;
}
</style>
