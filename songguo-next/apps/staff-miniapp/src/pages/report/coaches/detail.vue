<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@songguo/api-client";
import { fetchCoachAppointments } from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type {
  ReportCoachAppointmentDetail,
  ReportCoachAppointmentLine,
  ReportCoachSessionKind,
  ReportCoachSignInState,
} from "@/types/reports";

const session = useSessionStore();
const staffId = ref(0);
const staffName = ref("");
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth() + 1);
const sessionKind = ref<ReportCoachSessionKind>("all");
const loading = ref(true);
const loadingMore = ref(false);
const forbidden = ref(false);
const errorMessage = ref("");
const page = ref(1);
const lastPage = ref(1);
const detail = ref<ReportCoachAppointmentDetail | null>(null);

const canView = computed(() => session.can("report.coach.read"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");
const sessionKindOptions = [
  { value: "all" as const, label: "全部" },
  { value: "group" as const, label: "团课" },
  { value: "private" as const, label: "私教" },
];

const coachLabel = computed(() => detail.value?.coach.staffName?.trim() || staffName.value || `员工 #${staffId.value}`);

const totalsLabel = computed(() => {
  if (!detail.value) return "";
  const { appointmentCount, signedInCount, cancelledCount, absentCount } = detail.value.totals;
  return `预约 ${appointmentCount} · 签到 ${signedInCount} · 取消 ${cancelledCount} · 缺席 ${absentCount}`;
});

const signInLabels: Record<ReportCoachSignInState, string> = {
  booked: "已预约",
  signed_in: "已签到",
  cancelled: "已取消",
  absent: "缺席",
  waitlisted: "候补",
};

function memberLabel(line: ReportCoachAppointmentLine) {
  return line.memberName?.trim() || line.memberNo || "会员";
}

function formatDateTime(value: string | null) {
  if (!value) return "时间待定";
  return value.replace("T", " ").slice(0, 16);
}

function lineMeta(line: ReportCoachAppointmentLine) {
  const kind = line.sessionKind === "group" ? "团课" : line.sessionKind === "private" ? "私教" : "课程";
  const course = line.courseName || kind;
  return `${formatDateTime(line.startsAt)} · ${course} · ${signInLabels[line.signInState] || line.signInState}`;
}

function resolveError(error: unknown) {
  if (error instanceof ApiError && error.statusCode === 403) {
    forbidden.value = true;
    return;
  }
  errorMessage.value = error instanceof Error ? error.message : "预约明细加载失败";
}

async function load(reset = true) {
  if (!session.currentSiteId || !staffId.value || !canView.value) {
    loading.value = false;
    uni.stopPullDownRefresh();
    return;
  }

  if (reset) {
    loading.value = true;
    forbidden.value = false;
    errorMessage.value = "";
    page.value = 1;
    lastPage.value = 1;
    detail.value = null;
  } else {
    loadingMore.value = true;
  }

  try {
    const requestedPage = reset ? 1 : page.value + 1;
    const response = await fetchCoachAppointments(
      session.currentSiteId,
      staffId.value,
      selectedYear.value,
      selectedMonth.value,
      sessionKind.value,
      requestedPage,
    );
    if (reset) {
      detail.value = response;
    } else if (detail.value) {
      detail.value = {
        ...response,
        items: [...detail.value.items, ...response.items],
      };
    } else {
      detail.value = response;
    }
    page.value = requestedPage;
    lastPage.value = response.pagination.lastPage;
  } catch (error) {
    if (reset) {
      detail.value = null;
      resolveError(error);
    } else {
      uni.showToast({ title: error instanceof Error ? error.message : "加载失败", icon: "none" });
    }
  } finally {
    loading.value = false;
    loadingMore.value = false;
    uni.stopPullDownRefresh();
  }
}

async function selectSessionKind(value: ReportCoachSessionKind) {
  if (sessionKind.value === value) return;
  sessionKind.value = value;
  await load();
}

async function loadMore() {
  if (loadingMore.value || page.value >= lastPage.value) return;
  await load(false);
}

onLoad((query) => {
  staffId.value = Number(query?.staffId || 0);
  staffName.value = decodeURIComponent(String(query?.staffName || ""));
  selectedYear.value = Number(query?.year || new Date().getFullYear());
  selectedMonth.value = Number(query?.month || new Date().getMonth() + 1);
});

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(() => load());
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <view class="header-row">
      <view>
        <text class="title">{{ coachLabel }}</text>
        <text class="subtitle">{{ currentSiteName }} · {{ selectedYear }}年{{ selectedMonth }}月</text>
      </view>
    </view>

    <u-empty v-if="forbidden || !canView" mode="permission" text="暂无教练月报权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

      <view class="section-title">课程类型</view>
      <view class="chip-row">
        <view
          v-for="option in sessionKindOptions"
          :key="option.value"
          class="chip"
          :class="{ active: option.value === sessionKind }"
          @click="selectSessionKind(option.value)"
        >
          {{ option.label }}
        </view>
      </view>

      <view v-if="totalsLabel" class="totals-card">{{ totalsLabel }}</view>

      <view v-if="detail" class="list-card">
        <view v-for="item in detail.items" :key="item.appointmentId" class="list-row">
          <view class="list-main">
            <text class="list-name">{{ memberLabel(item) }}</text>
            <text class="list-meta">{{ lineMeta(item) }}</text>
          </view>
        </view>
        <u-empty v-if="!detail.items.length" mode="list" text="暂无预约记录" />
        <u-loadmore
          v-else
          :status="page >= lastPage ? 'nomore' : loadingMore ? 'loading' : 'loadmore'"
          @loadmore="loadMore"
        />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.title,
.subtitle,
.list-name,
.list-meta {
  display: block;
}

.title {
  font-size: 38rpx;
  font-weight: 600;
}

.subtitle,
.list-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-sm;
}

.chip {
  padding: 12rpx 28rpx;
  border: 1rpx solid $color-border;
  border-radius: 999rpx;
  background: $color-surface;
  font-size: 26rpx;
}

.chip.active {
  border-color: #ed920f;
  color: #ed920f;
  background: #fdf3e3;
}

.totals-card,
.list-card {
  margin-top: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.list-row {
  padding: $spacing-sm 0;
  border-bottom: 1rpx solid $color-border;
}

.list-row:last-child {
  border-bottom: none;
}

.list-name {
  font-size: 28rpx;
  font-weight: 500;
}
</style>
