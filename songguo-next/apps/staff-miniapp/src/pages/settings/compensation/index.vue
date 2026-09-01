<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchAllStaffCourseCatalog } from "@/api/catalog";
import { fetchCourseCompensationRuleSets } from "@/api/compensation";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CourseCatalogItem } from "@/types/catalog";
import type { CourseCompensationRule } from "@/types/compensation";

interface CourseRuleRow extends CourseCatalogItem {
  rule: CourseCompensationRule | null;
}

const session = useSessionStore();
const loading = ref(true);
const rows = ref<CourseRuleRow[]>([]);
const canRead = computed(() => session.can("compensation.rule.read"));
const canWrite = computed(() => session.can("compensation.rule.write"));

async function load() {
  if (!session.currentSiteId || !canRead.value) { loading.value = false; return; }
  loading.value = true;
  try {
    const catalog = await fetchAllStaffCourseCatalog(session.currentSiteId);
    const rules = await fetchCourseCompensationRuleSets(
      session.currentSiteId,
      catalog.map((course) => course.id),
    );
    rows.value = catalog.map((course) => ({ ...course, rule: rules.get(course.id) ?? null }));
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "课程薪酬加载失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}

function openRule(row: CourseRuleRow) {
  if (!canWrite.value) {
    uni.showToast({ title: "暂无课程薪酬编辑权限", icon: "none" });
    return;
  }
  uni.navigateTo({
    url: `/pages/settings/compensation/course-rule?courseId=${row.id}&name=${encodeURIComponent(row.name)}`,
  });
}

function rateSummary(rule: CourseCompensationRule | null) {
  if (!rule) return "未配置";
  const active = rule.roleRates.filter((item) => item.rateBasisPoints > 0);
  if (!active.length) return "仅课时费，无耗卡比例";
  return active.map((item) => `${item.roleName} ${item.rateBasisPoints / 100}%`).join(" · ");
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
  <view v-if="!loading && canRead" class="page-container compensation-page">
    <u-alert
      type="warning"
      description="此处只配置薪酬规则。实际课时费、耗卡价值与提成均由后端结算，员工端不自行计算。"
    />
    <view v-if="rows.length" class="course-list">
      <view v-for="row in rows" :key="row.id" class="course-row" @tap="openRule(row)">
        <view class="course-mark" :class="row.courseType">{{ row.courseType === "private" ? "私" : "团" }}</view>
        <view class="course-main">
          <view class="course-title-row">
            <text class="course-name">{{ row.name }}</text>
            <text v-if="row.rule" class="version">v{{ row.rule.version }}</text>
          </view>
          <text class="course-meta">
            {{ row.rule ? `课时费 ¥${row.rule.sessionFee}` : "尚未设置课时费" }}
          </text>
          <text class="course-rate">{{ rateSummary(row.rule) }}</text>
          <text v-if="row.rule?.effectiveFrom" class="effective">{{ row.rule.effectiveFrom }} 起生效</text>
        </view>
        <u-icon name="arrow-right" size="16" color="#bfbfbf" />
      </view>
    </view>
    <u-empty v-else mode="list" text="请先创建课程" />
  </view>
  <u-empty v-else-if="!loading" mode="permission" text="暂无课程薪酬查看权限" />
</template>

<style scoped lang="scss">
.compensation-page { padding-bottom: 60rpx; }
.course-list { margin-top: 20rpx; overflow: hidden; background: #fff; border-radius: $radius-lg; }
.course-row { display: flex; align-items: center; gap: 18rpx; padding: 26rpx 22rpx; border-bottom: 1rpx solid #f1f1f1; }
.course-row:last-child { border-bottom: 0; }
.course-mark { display: flex; flex-shrink: 0; align-items: center; justify-content: center; width: 68rpx; height: 68rpx; color: #fff; background: #5fa3ea; border-radius: 16rpx; font-size: 25rpx; font-weight: 600; }
.course-mark.private { background: #696b99; }
.course-main { flex: 1; min-width: 0; }
.course-title-row { display: flex; align-items: center; gap: 10rpx; }
.course-name { overflow: hidden; font-size: 28rpx; font-weight: 600; white-space: nowrap; text-overflow: ellipsis; }
.version { color: $color-text-tertiary; font-size: 20rpx; }
.course-meta, .course-rate, .effective { display: block; margin-top: 6rpx; font-size: 22rpx; }
.course-meta { color: $color-text-secondary; }
.course-rate { overflow: hidden; color: #8b6c00; white-space: nowrap; text-overflow: ellipsis; }
.effective { color: $color-text-tertiary; }
</style>
