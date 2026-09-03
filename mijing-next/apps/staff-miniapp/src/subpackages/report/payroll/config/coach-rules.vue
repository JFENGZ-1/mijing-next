<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@mijing/api-client";
import { fetchCoachRules, updateCoachRules } from "@/api/payroll";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { PayrollCoachRuleCourseRow, PayrollCoachRules } from "@/types/payroll";

interface CourseDraft {
  courseId: number;
  courseName: string;
  unitPriceYuan: string;
  additionalPriceYuan: string;
  supplementalRatePercent: string;
  configured: boolean;
}

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const forbidden = ref(false);
const errorMessage = ref("");
const staffId = ref(0);
const rules = ref<PayrollCoachRules | null>(null);
const groupDrafts = ref<CourseDraft[]>([]);
const privateDrafts = ref<CourseDraft[]>([]);

const canRead = computed(() => session.can("payroll.config.read"));
const canWrite = computed(() => session.can("payroll.config.write"));
const coachMode = computed(() => rules.value?.coachConfig.mode);
const coachLabel = computed(() => rules.value?.coach.name || "教练");

function centsToYuan(cents: number | null) {
  if (cents === null || cents === undefined) return "";
  return (cents / 100).toFixed(2).replace(/\.00$/, "");
}

function yuanToCents(value: string) {
  const parsed = Number.parseFloat(value);
  if (!Number.isFinite(parsed) || parsed < 0) return null;
  return Math.round(parsed * 100);
}

function toDraft(row: PayrollCoachRuleCourseRow): CourseDraft {
  return {
    courseId: row.courseId,
    courseName: row.courseName,
    unitPriceYuan: row.configured ? centsToYuan(row.unitPriceCents) : "",
    additionalPriceYuan: row.additionalPriceCents !== null ? centsToYuan(row.additionalPriceCents) : "",
    supplementalRatePercent: row.supplementalRatePercent !== null ? String(row.supplementalRatePercent) : "",
    configured: row.configured,
  };
}

function hydrateDrafts(data: PayrollCoachRules) {
  groupDrafts.value = data.groupCourses.map(toDraft);
  privateDrafts.value = data.privateCourses.map(toDraft);
}

function resolveError(error: unknown) {
  if (error instanceof ApiError && error.statusCode === 403) {
    forbidden.value = true;
    return;
  }
  errorMessage.value = error instanceof Error ? error.message : "教练规则加载失败";
}

async function load() {
  if (!staffId.value) {
    loading.value = false;
    errorMessage.value = "缺少教练信息，请返回工资配置重新选择";
    uni.stopPullDownRefresh();
    return;
  }
  if (!session.currentSiteId || !canRead.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  forbidden.value = false;
  errorMessage.value = "";
  try {
    const data = await fetchCoachRules(session.currentSiteId, staffId.value);
    rules.value = data;
    hydrateDrafts(data);
  } catch (error) {
    rules.value = null;
    resolveError(error);
  } finally {
    loading.value = false;
  }
}

function buildGroupPayload() {
  return groupDrafts.value
    .map((draft) => {
      const unitPriceCents = yuanToCents(draft.unitPriceYuan);
      if (unitPriceCents === null) return null;
      const row: { courseId: number; unitPriceCents: number; supplementalRatePercent?: number } = {
        courseId: draft.courseId,
        unitPriceCents,
      };
      if (coachMode.value === "headcount" || coachMode.value === "amount") {
        const rate = Number.parseInt(draft.supplementalRatePercent, 10);
        if (Number.isFinite(rate)) row.supplementalRatePercent = rate;
      }
      return row;
    })
    .filter((row): row is NonNullable<typeof row> => row !== null);
}

function buildPrivatePayload() {
  return privateDrafts.value
    .map((draft) => {
      const unitPriceCents = yuanToCents(draft.unitPriceYuan);
      if (unitPriceCents === null) return null;
      const row: { courseId: number; unitPriceCents: number; additionalPriceCents?: number } = {
        courseId: draft.courseId,
        unitPriceCents,
      };
      const additionalPriceCents = yuanToCents(draft.additionalPriceYuan);
      if (additionalPriceCents !== null) row.additionalPriceCents = additionalPriceCents;
      return row;
    })
    .filter((row): row is NonNullable<typeof row> => row !== null);
}

async function save() {
  if (!session.currentSiteId || !staffId.value || !canWrite.value || saving.value) return;
  saving.value = true;
  errorMessage.value = "";
  try {
    const data = await updateCoachRules(session.currentSiteId, staffId.value, {
      groupCourses: buildGroupPayload(),
      privateCourses: buildPrivatePayload(),
    });
    rules.value = data;
    hydrateDrafts(data);
    uni.showToast({ title: "规则已保存", icon: "success" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

onLoad((options) => {
  staffId.value = Number(options?.staffId ?? 0);
});

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(async () => {
  await load();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="page-container">
    <text class="eyebrow">基础课时规则</text>
    <view class="title">{{ coachLabel }} · 工资规则</view>
    <text v-if="rules" class="subtitle">矩阵版本 v{{ rules.matrixVersion }}</text>

    <u-empty v-if="forbidden || !canRead" mode="permission" text="暂无工资配置权限" />
    <template v-else>
      <view v-if="errorMessage" class="error-card">
        <view>
          <text class="error-title">教练规则暂未加载</text>
          <text class="error-detail">{{ errorMessage }}</text>
        </view>
        <button class="retry-btn" @tap="load">重新加载</button>
      </view>

      <view v-if="rules" class="panel">
        <view class="section-title">团课</view>
        <u-empty v-if="!groupDrafts.length" mode="list" text="暂无团课" />
        <view v-for="draft in groupDrafts" :key="draft.courseId" class="course-card">
          <text class="course-name">{{ draft.courseName }}</text>
          <view class="field">
            <text class="field-label">单价 (元)</text>
            <u-input v-model="draft.unitPriceYuan" type="digit" placeholder="留空表示不配置" :disabled="!canWrite" />
          </view>
          <view v-if="coachMode === 'headcount' || coachMode === 'amount'" class="field">
            <text class="field-label">补充比例 (%)</text>
            <u-input
              v-model="draft.supplementalRatePercent"
              type="number"
              placeholder="0-99"
              :disabled="!canWrite"
            />
          </view>
        </view>

        <view class="section-title">私教</view>
        <u-empty v-if="!privateDrafts.length" mode="list" text="暂无私教课" />
        <view v-for="draft in privateDrafts" :key="draft.courseId" class="course-card">
          <text class="course-name">{{ draft.courseName }}</text>
          <view class="field">
            <text class="field-label">单价 (元)</text>
            <u-input v-model="draft.unitPriceYuan" type="digit" placeholder="留空表示不配置" :disabled="!canWrite" />
          </view>
          <view class="field">
            <text class="field-label">加人加价 (元)</text>
            <u-input v-model="draft.additionalPriceYuan" type="digit" placeholder="可选" :disabled="!canWrite" />
          </view>
        </view>

        <u-button v-if="canWrite" type="primary" :loading="saving" @click="save">保存规则</u-button>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.title {
  font-size: 36rpx;
  font-weight: 600;
}

.subtitle,
.eyebrow,
.error-title,
.error-detail,
.section-title,
.course-name,
.field-label {
  display: block;
}

.eyebrow {
  margin-bottom: 6rpx;
  color: #d98200;
  font-size: 22rpx;
  font-weight: 600;
}

.error-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: $spacing-md;
  margin-top: $spacing-md;
  padding: $spacing-md;
  border: 1rpx solid rgba(225, 82, 82, 0.18);
  border-radius: $radius-md;
  background: #fff6f5;
}

.error-title {
  color: $color-danger;
  font-size: 26rpx;
  font-weight: 600;
}

.error-detail {
  margin-top: 6rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
}

.retry-btn {
  flex: none;
  margin: 0;
  padding: 0 24rpx;
  color: $color-danger;
  font-size: 24rpx;
  line-height: 56rpx;
  border: 1rpx solid currentColor;
  border-radius: 999rpx;
  background: transparent;
}

.retry-btn::after {
  border: 0;
}

.subtitle {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.panel {
  margin-top: $spacing-md;
}

.section-title {
  margin-top: $spacing-lg;
  font-size: 28rpx;
  font-weight: 600;
}

.course-card {
  margin-top: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.course-name {
  font-size: 28rpx;
  font-weight: 600;
}

.field {
  margin-top: $spacing-sm;
}

.field-label {
  margin-bottom: $spacing-xs;
  font-size: 24rpx;
  color: $color-text-secondary;
}
</style>
