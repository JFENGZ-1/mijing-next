<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchCrmDashboardSummary, fetchCrmMemberFilterPresets } from "@/api/crm";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CrmDashboardSummary, CrmFilterPresetQuery, CrmMemberFilterPresets } from "@/types/crm";
import { CRM_MEMBER_FILTER_STORAGE_KEY } from "@/types/crm";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const dashboard = ref<CrmDashboardSummary | null>(null);
const presets = ref<CrmMemberFilterPresets | null>(null);

const canAnalyze = computed(() => session.can("crm.member.read") || session.can("report.read"));

const summaryCards = computed(() => [
  { label: "全部会员", value: dashboard.value?.totalCount ?? 0, query: { sumMode: "all" } },
  { label: "本月新增", value: dashboard.value?.monthCount ?? 0, query: { sumMode: "monthNew" } },
  { label: "有效会员", value: dashboard.value?.validUserCount ?? 0, query: { sumMode: "valid" } },
  { label: "无效会员", value: dashboard.value?.invalidUserCount ?? 0, query: { sumMode: "invalid" } },
  { label: "无卡会员", value: dashboard.value?.nocardUserCount ?? 0, query: { sumMode: "noCard" } },
  { label: "屏蔽会员", value: dashboard.value?.nologinUserCount ?? 0, query: { sumMode: "blocked" } },
]);

const runOffPresets = computed(() => presets.value?.runOffPresets ?? []);
const flagPresets = computed(() => (presets.value?.flagPresets ?? []).filter((item) => item.listSupported !== false));

const hints = [
  "有效会员：名下至少有一张有余额且在有效期内的会员卡",
  "无效会员：名下所有卡均已无余额或已过期",
  "风险/沉寂/流失会员：有有效卡但长时间未上课（按未上课天数分档）",
  "屏蔽会员：开启屏蔽功能后被屏蔽进入的会员",
];

async function load() {
  if (!canAnalyze.value) {
    loading.value = false;
    dashboard.value = null;
    presets.value = null;
    errorMessage.value = "";
    return;
  }
  if (!session.currentSiteId) {
    loading.value = false;
    errorMessage.value = "当前账号没有可用场馆";
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const [summaryResponse, presetResponse] = await Promise.all([
      fetchCrmDashboardSummary(session.currentSiteId),
      fetchCrmMemberFilterPresets(session.currentSiteId),
    ]);
    dashboard.value = summaryResponse.data;
    presets.value = presetResponse.data;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "会员分析加载失败";
  } finally {
    loading.value = false;
  }
}

function openMemberList(label: string, query: CrmFilterPresetQuery) {
  uni.setStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY, JSON.stringify({ label, query }));
  uni.switchTab({ url: "/pages/members/index" });
}

function goMembersHome() {
  uni.switchTab({ url: "/pages/members/index" });
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
    <view v-if="!canAnalyze" class="empty-perm">
      <u-empty mode="permission" text="暂无会员分析权限" />
      <view class="empty-hint">需要 crm.member.read 或 report.read 权限</view>
      <u-button type="primary" plain @click="goMembersHome">返回会员管理</u-button>
    </view>

    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

      <view class="sg-card">
        <text class="card-title">会员分析</text>
        <view class="grid">
          <view
            v-for="item in summaryCards"
            :key="item.label"
            class="grid-cell"
            @tap="openMemberList(item.label, item.query)"
          >
            <text class="cell-value">{{ item.value }}</text>
            <text class="cell-label">{{ item.label }}</text>
          </view>
        </view>
      </view>

      <view v-if="runOffPresets.length" class="sg-card block-card">
        <text class="card-title">未上课风险分层</text>
        <view
          v-for="item in runOffPresets"
          :key="item.runOff"
          class="preset-row"
          @tap="openMemberList(item.label, item.query)"
        >
          <text class="row-label">{{ item.label }}</text>
          <u-icon name="arrow-right" size="16" color="#bfbfbf" />
        </view>
      </view>

      <view v-if="flagPresets.length" class="sg-card block-card">
        <text class="card-title">上课会员</text>
        <view
          v-for="item in flagPresets"
          :key="item.flag"
          class="preset-row"
          @tap="openMemberList(item.label, item.query)"
        >
          <text class="row-label">{{ item.label }}</text>
          <u-icon name="arrow-right" size="16" color="#bfbfbf" />
        </view>
      </view>

      <view class="sg-card block-card">
        <text class="card-title">统计说明</text>
        <text v-for="hint in hints" :key="hint" class="hint-line">{{ hint }}</text>
      </view>

      <u-button class="back-home" plain @click="goMembersHome">返回会员列表</u-button>
    </template>
  </view>
</template>

<style scoped lang="scss">
.empty-perm {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 24rpx;
  padding: 80rpx 32rpx;
}

.empty-hint {
  color: $color-text-secondary;
  font-size: 24rpx;
  text-align: center;
}

.card-title {
  display: block;
  margin-bottom: 8rpx;
  font-size: 30rpx;
  font-weight: 600;
  color: $color-text;
}

.block-card {
  margin-top: $spacing-md;
}

.grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16rpx;
  margin-top: 16rpx;
}

.grid-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 28rpx 0;
  background: $color-page;
  border-radius: $radius-md;
}

.cell-value {
  font-size: 40rpx;
  font-weight: 600;
  color: $color-text;
}

.cell-label {
  margin-top: 10rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.preset-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx 0;
  border-bottom: 1rpx solid $color-page;

  &:last-child {
    border-bottom: none;
  }
}

.row-label {
  font-size: 28rpx;
  color: $color-text;
}

.hint-line {
  display: block;
  margin-top: 14rpx;
  color: $color-text-tertiary;
  font-size: 24rpx;
  line-height: 1.6;
}

.back-home {
  margin-top: 32rpx;
}
</style>
