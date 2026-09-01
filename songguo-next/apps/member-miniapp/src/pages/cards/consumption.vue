<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { getMemberConsumptionSettlements } from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberConsumptionSettlementItem } from "@/types/member";
import { formatApiErrorMessage } from "@/utils/api-error";
import { cardTypeLabel, formatIsoDate } from "@/utils/format";

const loading = ref(true);
const loadingMore = ref(false);
const errorMessage = ref("");
const items = ref<MemberConsumptionSettlementItem[]>([]);
const memberCardId = ref<number | undefined>();
const page = ref(1);
const lastPage = ref(1);
const totalValue = ref("0.00");
const pendingValue = ref("0.00");
const unvaluedCount = ref(0);

const scopeTitle = computed(() => (memberCardId.value ? "本卡耗卡价值" : "累计耗卡价值"));

function settlementStatusLabel(status: string) {
  if (status === "provisional") return "待日结";
  if (status === "final" || status === "finalized") return "已结算";
  if (status === "adjusted") return "已调整";
  if (status === "reversed") return "已冲正";
  return status || "—";
}

function settlementStatusClass(status: string) {
  if (status === "final" || status === "finalized") return "status--finalized";
  if (status === "provisional") return "status--pending";
  if (status === "reversed") return "status--reversed";
  return "status--adjusted";
}

function deductionLabel(item: MemberConsumptionSettlementItem) {
  if (item.cardType === "stored_value" && item.deductionAmount) return `扣 ¥${item.deductionAmount}`;
  if (item.cardType === "count" && item.deductionCount != null) return `扣 ${item.deductionCount} 次`;
  if (item.cardType === "period") return "按当日实际履约次数分摊";
  return "按卡课规则耗卡";
}

async function loadSettlements(reset = true) {
  if (reset) {
    loading.value = items.value.length === 0;
    page.value = 1;
    lastPage.value = 1;
  } else {
    loadingMore.value = true;
  }
  errorMessage.value = "";

  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) {
      if (!reset) page.value = Math.max(1, page.value - 1);
      errorMessage.value = "请先选择场馆";
      return;
    }
    const response = await getMemberConsumptionSettlements(tenant.tenantId, {
      memberCardId: memberCardId.value,
      page: page.value,
      perPage: 20,
    });
    items.value = reset ? response.data.items : [...items.value, ...response.data.items];
    lastPage.value = response.data.pagination.lastPage;
    totalValue.value = response.data.summary?.consumptionValue ?? "0.00";
    pendingValue.value = response.data.summary?.pendingValue ?? "0.00";
    unvaluedCount.value = response.data.summary?.unvaluedCount ?? 0;
  } catch (error) {
    if (!reset) page.value = Math.max(1, page.value - 1);
    errorMessage.value = formatApiErrorMessage(error, "耗卡记录加载失败");
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

async function loadMore() {
  if (loadingMore.value || page.value >= lastPage.value) return;
  page.value += 1;
  await loadSettlements(false);
}

onLoad((query) => {
  const id = Number(query?.cardId ?? 0);
  memberCardId.value = id > 0 ? id : undefined;
});

onShow(async () => {
  if (await requireMemberAuth()) await loadSettlements();
});

onPullDownRefresh(async () => {
  await loadSettlements();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="consumption-page">
    <u-alert
      v-if="errorMessage"
      type="error"
      :description="errorMessage"
      :custom-style="{ marginBottom: '24rpx' }"
    />

    <view class="summary-card">
      <view>
        <view class="summary-label">{{ scopeTitle }}</view>
        <view class="summary-value"><text>¥</text>{{ totalValue }}</view>
      </view>
      <view class="summary-pending">
        <view>待日结</view>
        <text>¥{{ pendingValue }}</text>
      </view>
    </view>

    <view class="explain-card">
      <u-icon name="info-circle" size="18" color="#696B99" />
      <text>
        金额由后端按实付价值、卡类型及实际履约统一结算；期限卡当天结束前可能重新分摊。
        <template v-if="unvaluedCount > 0">其中 {{ unvaluedCount }} 笔历史或人工权益价值待核定，未计入上方金额。</template>
      </text>
    </view>

    <u-empty v-if="items.length === 0 && !errorMessage" mode="list" text="暂无耗卡记录" />

    <view v-for="item in items" :key="item.id" class="settlement-card">
      <view class="settlement-head">
        <view>
          <view class="course-name">{{ item.courseName || "课程" }}</view>
          <view class="service-date">{{ formatIsoDate(item.startsAt || item.serviceDate) }}</view>
        </view>
        <view class="status" :class="settlementStatusClass(item.status)">
          {{ settlementStatusLabel(item.status) }}
        </view>
      </view>

      <view class="settlement-main">
        <view>
          <view class="card-name">{{ item.memberCardName || "会员卡" }}</view>
          <view class="deduction">
            {{ cardTypeLabel(item.cardType) }} · {{ deductionLabel(item) }}
          </view>
          <view v-if="item.coachName" class="coach">授课：{{ item.coachName }}</view>
        </view>
        <view class="value">
          <view class="value-label">耗卡价值</view>
          <view class="value-amount">
            {{ item.consumptionValue == null ? "待核定" : `¥${item.consumptionValue}` }}
          </view>
        </view>
      </view>

      <view v-if="item.calculationVersion" class="version">
        结算版本 {{ item.calculationVersion }}
      </view>
    </view>

    <view v-if="page < lastPage" class="loadmore-wrap">
      <u-loadmore
        :status="loadingMore ? 'loading' : 'loadmore'"
        loadmore-text="加载更多"
        @loadmore="loadMore"
      />
    </view>

    <bottom-logo v-if="items.length" />
  </view>
</template>

<style scoped lang="scss">
.consumption-page {
  min-height: 100vh;
  padding: 24rpx 28rpx 0;
  box-sizing: border-box;
  background: $color-page;
}

.summary-card,
.settlement-card,
.explain-card {
  background: $color-surface;
  border-radius: $radius-md;
}

.summary-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 30rpx 32rpx;
}

.summary-label,
.value-label,
.service-date,
.deduction,
.coach,
.version {
  color: $color-text-secondary;
  font-size: 22rpx;
}

.summary-value {
  margin-top: 8rpx;
  color: $color-text;
  font-size: 48rpx;
  font-weight: 700;
}

.summary-value text {
  margin-right: 4rpx;
  font-size: 26rpx;
}

.summary-pending {
  text-align: right;
  color: $color-text-secondary;
  font-size: 22rpx;
}

.summary-pending text {
  display: block;
  margin-top: 8rpx;
  color: #fc8c00;
  font-size: 28rpx;
  font-weight: 600;
}

.explain-card {
  display: flex;
  align-items: flex-start;
  gap: 14rpx;
  margin: 20rpx 0 24rpx;
  padding: 22rpx 24rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
  line-height: 1.6;
}

.settlement-card {
  margin-bottom: 20rpx;
  padding: 26rpx 28rpx;
}

.settlement-head,
.settlement-main {
  display: flex;
  justify-content: space-between;
  gap: 24rpx;
}

.settlement-head {
  align-items: flex-start;
  padding-bottom: 20rpx;
  border-bottom: 1rpx solid $color-border;
}

.settlement-main {
  align-items: center;
  padding-top: 22rpx;
}

.course-name,
.card-name {
  color: $color-text;
  font-size: 28rpx;
  font-weight: 600;
}

.service-date,
.deduction,
.coach {
  margin-top: 8rpx;
}

.status {
  flex-shrink: 0;
  padding: 6rpx 14rpx;
  border-radius: 18rpx;
  font-size: 21rpx;
}

.status--finalized { color: #16855f; background: #e8f8f2; }
.status--pending { color: #ad6200; background: #fff4df; }
.status--reversed { color: #a42a42; background: #fff0f3; }
.status--adjusted { color: #4e538c; background: #f0f1fb; }

.value {
  flex-shrink: 0;
  text-align: right;
}

.value-amount {
  margin-top: 6rpx;
  color: $color-text;
  font-size: 32rpx;
  font-weight: 700;
}

.version {
  margin-top: 18rpx;
  padding-top: 16rpx;
  border-top: 1rpx dashed $color-border;
}

.loadmore-wrap {
  padding: 20rpx 0 30rpx;
}
</style>
