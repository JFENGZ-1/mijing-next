<script setup lang="ts">
// 批量操作：批量停课 / 整体换课（区间勾选模式）
// 复制课表/批量清除/下载课表已并入「课程/排课」页（对标原版 pagesCourse/index/index）
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import {
  batchChangeCourseStaffScheduleSessions,
  batchSuspendStaffScheduleSessions,
  fetchStaffScheduleSessions,
} from "@/api/scheduling";
import { fetchStaffCourseCatalog } from "@/api/catalog";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CourseCatalogItem } from "@/types/catalog";
import type { ScheduleSession } from "@/types/scheduling";
import { createCommandKey } from "@/utils/command-key";
import { formatSessionTime, sessionStatusLabel, todayIsoDate } from "@/utils/format";

const session = useSessionStore();
const checking = ref(true);

const canSuspend = computed(() => session.can("schedule.batch.suspend"));
const canChangeCourse = computed(() => session.can("schedule.session.write"));
const hasAnyPermission = computed(() => canSuspend.value || canChangeCourse.value);

type ListAction = "suspend" | "change";
const listAction = ref<ListAction>("suspend");
const listFrom = ref(todayIsoDate());
const listTo = ref(todayIsoDate());
const listLoading = ref(false);
const listSubmitting = ref(false);
const listSessions = ref<ScheduleSession[]>([]);
const listSelected = ref<number[]>([]);
const listReason = ref("");
const courses = ref<CourseCatalogItem[]>([]);
const targetCourseId = ref(0);
const targetCourseIndex = computed(() => courses.value.findIndex((item) => item.id === targetCourseId.value));
const targetCourseLabel = computed(
  () => courses.value.find((item) => item.id === targetCourseId.value)?.name || "请选择目标课程",
);
const allSelected = computed(
  () => listSessions.value.length > 0 && listSelected.value.length === listSessions.value.length,
);

function switchAction(action: ListAction) {
  if (action === "suspend" && !canSuspend.value) {
    uni.showToast({ title: "暂无批量停课权限", icon: "none" });
    return;
  }
  if (action === "change" && !canChangeCourse.value) {
    uni.showToast({ title: "暂无换课权限", icon: "none" });
    return;
  }
  listAction.value = action;
}

async function loadListSessions() {
  if (!session.currentSiteId) return;
  if (listFrom.value > listTo.value) {
    uni.showToast({ title: "开始日期不能晚于结束日期", icon: "none" });
    return;
  }
  listLoading.value = true;
  try {
    const response = await fetchStaffScheduleSessions(
      session.currentSiteId,
      `${listFrom.value}T00:00:00`,
      `${listTo.value}T23:59:59`,
    );
    listSessions.value = response.items.filter((item) => item.status !== "cancelled");
    listSelected.value = listSessions.value.map((item) => item.id);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "课表加载失败", icon: "none" });
  } finally {
    listLoading.value = false;
  }
}

function toggleListSession(id: number) {
  listSelected.value = listSelected.value.includes(id)
    ? listSelected.value.filter((item) => item !== id)
    : [...listSelected.value, id];
}

function toggleListAll() {
  listSelected.value = allSelected.value ? [] : listSessions.value.map((item) => item.id);
}

async function loadCoursesIfNeeded() {
  if (courses.value.length || !session.currentSiteId || !session.can("course-catalog.read")) return;
  try {
    const catalog = await fetchStaffCourseCatalog(session.currentSiteId, 1, 100, undefined, "group");
    courses.value = catalog.items;
  } catch {
    courses.value = [];
  }
}

function onTargetCourseChange(event: { detail: { value: string | number } }) {
  const course = courses.value[Number(event.detail.value)];
  targetCourseId.value = course?.id || 0;
}

async function submitListAction() {
  if (!session.currentSiteId || !listSelected.value.length) return;
  if (listAction.value === "suspend" && !listReason.value.trim()) {
    uni.showToast({ title: "请填写停课原因", icon: "none" });
    return;
  }
  if (listAction.value === "change" && !targetCourseId.value) {
    uni.showToast({ title: "请选择目标课程", icon: "none" });
    return;
  }

  const confirmText = listAction.value === "suspend"
    ? {
        title: "是否全部停课？",
        content: "改为停课状态后，会员将不可预约；如已有会员预约，将取消预约并下发停课通知。",
      }
    : {
        title: "是否整体换课？",
        content: `将把所选 ${listSelected.value.length} 节课更换为「${targetCourseLabel.value}」。此为批量操作，请谨慎操作。`,
      };
  const confirmation = await uni.showModal(confirmText);
  if (!confirmation.confirm) return;

  listSubmitting.value = true;
  try {
    if (listAction.value === "suspend") {
      const result = await batchSuspendStaffScheduleSessions(session.currentSiteId, {
        commandKey: createCommandKey(),
        sessionIds: listSelected.value,
        reason: listReason.value.trim(),
      });
      uni.showToast({ title: `停课成功 ${result.succeededSessionIds.length} 节`, icon: "success" });
    } else {
      const result = await batchChangeCourseStaffScheduleSessions(session.currentSiteId, {
        commandKey: createCommandKey(),
        sessionIds: listSelected.value,
        targetCourseId: targetCourseId.value,
      });
      uni.showToast({ title: `换课完成 ${result.changedCount ?? listSelected.value.length} 节`, icon: "success" });
    }
    await loadListSessions();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "批量操作失败", icon: "none" });
  } finally {
    listSubmitting.value = false;
  }
}

onShow(async () => {
  checking.value = true;
  const authenticated = await requireStaffAuth();
  checking.value = false;
  if (!authenticated) return;
});
</script>

<template>
  <u-loading-page :loading="checking" />
  <view v-if="!checking" class="page-shell">
    <view class="body-sheet">
      <u-empty v-if="!hasAnyPermission" mode="permission" text="当前账号暂无批量操作权限" />

      <template v-else>
        <view class="sub-tools">
          <text class="sub-tool" :class="{ active: listAction === 'suspend' }" @tap="switchAction('suspend')">批量停课</text>
          <text class="sub-tool" :class="{ active: listAction === 'change' }" @tap="switchAction('change')">整体换课</text>
        </view>

        <view class="list-panel">
          <view class="date-row">
            <picker mode="date" :value="listFrom" @change="listFrom = String($event.detail.value)">
              <view class="picker-field">{{ listFrom }}</view>
            </picker>
            <text class="date-sep">至</text>
            <picker mode="date" :value="listTo" @change="listTo = String($event.detail.value)">
              <view class="picker-field">{{ listTo }}</view>
            </picker>
            <view class="load-btn" @tap="loadListSessions">{{ listLoading ? "加载中" : "加载课表" }}</view>
          </view>

          <template v-if="listAction === 'change'">
            <picker
              :range="courses.map((item) => item.name)"
              :value="targetCourseIndex >= 0 ? targetCourseIndex : 0"
              @change="onTargetCourseChange"
              @tap="loadCoursesIfNeeded"
            >
              <view class="picker-field wide">目标课程：{{ targetCourseLabel }}</view>
            </picker>
          </template>
          <template v-else>
            <input v-model="listReason" class="reason-input" maxlength="200" placeholder="停课原因（会员将收到停课通知）" />
          </template>

          <view v-if="listSessions.length" class="select-row">
            <text class="select-count">已选 {{ listSelected.length }} / {{ listSessions.length }} 节</text>
            <text class="link-btn" @tap="toggleListAll">{{ allSelected ? "取消全选" : "全选" }}</text>
          </view>
          <view
            v-for="item in listSessions"
            :key="item.id"
            class="session-row"
            @tap="toggleListSession(item.id)"
          >
            <u-icon
              :name="listSelected.includes(item.id) ? 'checkmark-circle-fill' : 'checkmark-circle'"
              :color="listSelected.includes(item.id) ? '#ed920f' : '#bfbfbf'"
              size="20"
            />
            <view class="session-main">
              <text class="session-title">{{ item.courseName || "未命名课程" }}</text>
              <text class="session-meta">
                {{ formatSessionTime(item.startsAt, item.endsAt) }} · {{ sessionStatusLabel(item.status) }} · 预约
                {{ item.bookedCount }}/{{ item.capacity }}
              </text>
            </view>
          </view>

          <button
            v-if="listSessions.length"
            class="submit-btn"
            :disabled="listSubmitting || !listSelected.length"
            @tap="submitListAction"
          >
            {{ listSubmitting ? "提交中..." : listAction === "suspend" ? "批量停课" : "整体换课" }}
          </button>
          <view v-else class="guide-text">选择日期区间后点击「加载课表」，勾选需要操作的排课。</view>
        </view>

        <view class="brand-footer">觅境约课</view>
      </template>
    </view>
  </view>
</template>

<style scoped lang="scss">
.page-shell {
  min-height: 100vh;
  background: $color-brand-yellow;
}

.body-sheet {
  min-height: 100vh;
  padding: 30rpx 28rpx 60rpx;
  background: #fff;
  border-radius: 24rpx 24rpx 0 0;
  box-sizing: border-box;
}

.sub-tools {
  display: flex;
  gap: 20rpx;
  padding-bottom: 20rpx;
  border-bottom: 1rpx solid #f0f0f0;
}

.sub-tool {
  padding: 12rpx 30rpx;
  border: 1rpx solid $color-border;
  border-radius: 999rpx;
  color: $color-text-secondary;
  font-size: 24rpx;

  &.active {
    border-color: $color-primary;
    background: rgba(237, 146, 15, 0.08);
    color: $color-primary;
  }
}

.guide-text {
  padding: 60rpx 20rpx;
  color: $color-text-disabled;
  font-size: 24rpx;
  line-height: 1.7;
  text-align: center;
}

.list-panel {
  padding-top: 24rpx;
}

.date-row {
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.picker-field {
  padding: 16rpx 22rpx;
  background: $color-page;
  border-radius: 12rpx;
  color: $color-text;
  font-size: 26rpx;

  &.wide {
    margin-top: 16rpx;
  }
}

.date-sep {
  color: $color-text-secondary;
  font-size: 24rpx;
}

.load-btn {
  margin-left: auto;
  padding: 14rpx 26rpx;
  border-radius: 999rpx;
  background: $color-brand-yellow;
  color: $color-text;
  font-size: 24rpx;
}

.reason-input {
  margin-top: 16rpx;
  padding: 20rpx 24rpx;
  background: $color-page;
  border-radius: 12rpx;
  color: $color-text;
  font-size: 26rpx;
}

.select-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 24rpx 0 8rpx;
}

.select-count {
  font-size: 26rpx;
  font-weight: 600;
  color: $color-text;
}

.link-btn {
  color: $color-primary;
  font-size: 24rpx;
}

.session-row {
  display: flex;
  align-items: flex-start;
  gap: 16rpx;
  padding: 20rpx 0;
  border-bottom: 1rpx solid #f5f5f5;
}

.session-main {
  flex: 1;
  min-width: 0;
}

.session-title {
  display: block;
  font-size: 28rpx;
  font-weight: 600;
  color: $color-text;
}

.session-meta {
  display: block;
  margin-top: 8rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.submit-btn {
  width: 458rpx;
  height: 83rpx;
  margin: 40rpx auto 0;
  line-height: 83rpx;
  background: $color-brand-yellow;
  border-radius: 42rpx;
  color: $color-text;
  font-size: 32rpx;
  font-weight: 500;

  &[disabled] {
    opacity: 0.5;
    color: $color-text;
    background: $color-brand-yellow;
  }
}

.submit-btn::after {
  border: 0;
}

.brand-footer {
  margin: 90rpx 0 20rpx;
  color: #d8d8d8;
  font-size: 26rpx;
  letter-spacing: 6rpx;
  text-align: center;
}
</style>
