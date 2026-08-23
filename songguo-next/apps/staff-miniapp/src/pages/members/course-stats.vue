<script setup lang="ts">
/** 对标原版 pageMember/details/courseDetail：团课/私教/旷课统计 */
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { fetchMemberBookingHistory } from "@/api/crm";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffBookingHistoryItem } from "@/types/crm";
import CustomNav from "@/components/custom-nav/custom-nav.vue";
import FfBottomLogo from "@/components/ff-bottom-logo/ff-bottom-logo.vue";
import CalendarMonthPicker from "@/components/calendar-month-picker/calendar-month-picker.vue";

const session = useSessionStore();
const memberId = ref(0);
const userName = ref("会员");
const userFaceurl = ref("");
const mode = ref<0 | 1 | 2>(0);
const loading = ref(true);
const errorMessage = ref("");
const allItems = ref<StaffBookingHistoryItem[]>([]);
const now = new Date();
const year = ref(now.getFullYear());
const month = ref(now.getMonth() + 1);
const showMonthPicker = ref(false);

const titleName = computed(() => {
  if (mode.value === 1) return "私教";
  if (mode.value === 2) return "旷课";
  return "团课";
});

const canRead = computed(() => session.can("booking.member-history.list"));

function matchesMode(item: StaffBookingHistoryItem) {
  if (mode.value === 2) return item.status === "absent";
  const kind = item.sessionKind || item.courseType || "";
  if (mode.value === 1) return kind === "private";
  return kind === "group" || kind === "team" || (!kind && item.status !== "absent");
}

const filtered = computed(() => allItems.value.filter(matchesMode));

const monthItems = computed(() =>
  filtered.value.filter((item) => {
    if (!item.startsAt) return false;
    const d = new Date(item.startsAt);
    return d.getFullYear() === year.value && d.getMonth() + 1 === month.value;
  }),
);

/** 月份切换后始终按当前筛选重算，不锁死 query 入参 */
const monthCount = computed(() => monthItems.value.length);
const totalCount = computed(() => filtered.value.length);

async function load() {
  if (!session.currentSiteId || !memberId.value || !canRead.value) {
    loading.value = false;
    if (!canRead.value) errorMessage.value = "";
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await fetchMemberBookingHistory(session.currentSiteId, memberId.value, "past");
    allItems.value = response.data?.items ?? [];
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "预约记录加载失败";
    allItems.value = [];
  } finally {
    loading.value = false;
  }
}

function statusLabel(status: string) {
  return ({
    confirmed: "已预约",
    waitlisted: "候补",
    cancelled: "已取消",
    absent: "缺席",
    completed: "已完成",
  } as Record<string, string>)[status] || status;
}

function statusColor(status: string) {
  if (status === "absent") return "#dc3c5c";
  if (status === "cancelled") return "#989898";
  if (status === "completed") return "#22c788";
  return "#ed920f";
}

function formatRange(item: StaffBookingHistoryItem) {
  if (!item.startsAt) return "--";
  const start = item.startsAt.slice(5, 16).replace("T", " ");
  const end = item.endsAt ? item.endsAt.slice(11, 16) : "";
  return end ? `${start}~${end}` : start;
}

function openMonthPicker() {
  showMonthPicker.value = true;
}

function onMonthChange(payload: { year: number; month: number }) {
  year.value = payload.year;
  month.value = payload.month;
}

onLoad((query) => {
  memberId.value = Number(query?.id ?? 0);
  userName.value = decodeURIComponent(String(query?.name ?? "会员"));
  userFaceurl.value = decodeURIComponent(String(query?.avatar ?? ""));
  mode.value = (Number(query?.mode ?? 0) as 0 | 1 | 2) || 0;
});

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page">
    <CustomNav :text="userName" bg="#FFFFFF" :head-url="userFaceurl" />
    <view class="report-container">
      <view class="panel">
        <view class="content">
          <view class="time_axis" @tap="openMonthPicker">
            <view class="time">
              <text>{{ year }}年{{ month }}月</text>
              <u-icon name="calendar" color="#22C788" size="29" />
            </view>
          </view>

          <view class="top_data">
            <view class="more-data">
              <view class="item">
                <view class="info">
                  <text class="info_num">{{ monthCount }}</text>
                  <view>本月{{ titleName }}（节）</view>
                </view>
              </view>
              <view class="item">
                <view class="info">
                  <text class="info_num">{{ totalCount }}</text>
                  <view>累计{{ titleName }}（节）</view>
                </view>
              </view>
            </view>
          </view>
          <u-gap bg-color="#f5f5f5" height="24" />

          <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
          <u-empty v-if="!canRead" mode="permission" text="暂无预约记录权限" />

          <view v-else class="statistics">
            <view v-if="monthItems.length" class="list">
              <view v-for="item in monthItems" :key="item.id" class="row">
                <view class="photo_img">
                  <view class="avatar-fallback">{{ (item.coachName || item.courseName || "?").slice(0, 1) }}</view>
                </view>
                <view class="info_box">
                  <view class="first_row">
                    <text class="course_name">{{ item.courseName || item.coachName || "课程" }}</text>
                    <text class="status" :style="{ color: statusColor(item.status) }">{{ statusLabel(item.status) }}</text>
                  </view>
                  <view class="second_row">
                    <text v-if="item.coachName">{{ item.coachName }}</text>
                    <text v-if="item.roomName"> · {{ item.roomName }}</text>
                  </view>
                  <view class="third_row">{{ formatRange(item) }}</view>
                  <view v-if="item.staffNotes" class="remark">备注：{{ item.staffNotes }}</view>
                </view>
              </view>
            </view>
            <view v-else class="noCourseData">
              <u-icon name="calendar" size="72" color="#dadada" />
              <text>本月没有预约记录哦</text>
            </view>
          </view>
        </view>
      </view>
      <FfBottomLogo />
    </view>
  </view>

  <CalendarMonthPicker
    v-model:show="showMonthPicker"
    :year="year"
    :month="month"
    @change="onMonthChange"
  />
</template>

<style scoped lang="scss">
.page { min-height: 100vh; background: #fff; }
.report-container { min-height: 100vh; padding-top: calc(var(--status-bar-height, 20px) + 44px); background: #fff; }
.panel { min-height: 1230rpx; background: #fff; }
.time_axis { display: flex; justify-content: center; padding: 28rpx 0 10rpx; }
.time { display: flex; align-items: center; gap: 10rpx; color: #181818; font-size: 28rpx; }
.top_data { padding: 20rpx 0 10rpx; }
.more-data { display: flex; justify-content: space-around; }
.item .info { display: flex; flex-direction: column; align-items: center; color: #989898; font-size: 22rpx; }
.info_num { margin-bottom: 8rpx; color: #ed920f; font-size: 48rpx; font-weight: 500; }
.statistics { padding: 0 0 120rpx; }
.list { padding: 0 28rpx; }
.row { display: flex; gap: 20rpx; padding: 28rpx 0; border-bottom: 1rpx solid #f0f0f0; }
.photo_img { flex-shrink: 0; }
.avatar-fallback {
  display: flex; align-items: center; justify-content: center;
  width: 72rpx; height: 72rpx; border-radius: 50%;
  background: #fbd128; color: #181818; font-size: 28rpx; font-weight: 600;
}
.info_box { flex: 1; min-width: 0; }
.first_row { display: flex; justify-content: space-between; gap: 16rpx; }
.course_name { color: #181818; font-size: 28rpx; font-weight: 500; }
.status { font-size: 24rpx; }
.second_row, .third_row { margin-top: 8rpx; color: #989898; font-size: 22rpx; }
.remark { margin-top: 8rpx; color: #7e7e7e; font-size: 22rpx; }
.noCourseData {
  display: flex; flex-direction: column; align-items: center; gap: 16rpx;
  padding: 120rpx 0; color: #bfbfbf; font-size: 26rpx;
}
</style>
