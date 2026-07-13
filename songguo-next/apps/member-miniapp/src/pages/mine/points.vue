<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberPointsLedger } from "@/api/member";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberPointLedger, MemberPointLedgerEntry } from "@/types/member";
import { formatIsoDate } from "@/utils/format";

const loadingMore = ref(false);
const errorMessage = ref("");
const ledger = ref<MemberPointLedger | null>(null);
const page = ref(1);
const loading = ref(true);

function entryAmount(item: MemberPointLedgerEntry) {
  const prefix = item.direction === "credit" ? "+" : "-";
  return `${prefix}${Math.abs(item.amountDelta)}`;
}

async function load() {
  loading.value = true;
  try {
    await loadLedger();
  } finally {
    loading.value = false;
  }
}

async function loadLedger(reset = true) {
  if (!reset) {
    loadingMore.value = true;
  } else {
    page.value = 1;
    ledger.value = null;
  }
  errorMessage.value = "";

  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) {
      errorMessage.value = "请先选择场馆";
      return;
    }
    const response = await getMemberPointsLedger(tenant.tenantId, page.value);
    if (reset || !ledger.value) {
      ledger.value = response.data;
    } else {
      ledger.value = {
        ...response.data,
        items: [...ledger.value.items, ...response.data.items],
      };
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "积分明细加载失败";
  } finally {
    loadingMore.value = false;
  }
}

async function loadMore() {
  if (!ledger.value || loadingMore.value) return;
  if (page.value >= ledger.value.pagination.lastPage) return;
  page.value += 1;
  await loadLedger(false);
}

onShow(async () => { if (await requireMemberAuth()) await load(); });
onPullDownRefresh(async () => { await load(); uni.stopPullDownRefresh(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="points-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

    <template v-if="ledger">
      <view class="points-head">
        <view class="summarize">
          <view class="num">{{ ledger.totalPoint }}</view>
          <view class="num-text">有效积分</view>
        </view>
      </view>

      <view class="setting-wrap">
        <u-empty v-if="ledger.items.length === 0" mode="list" text="~ 没有积分记录 ~" />

        <view v-else class="list">
          <view v-for="(item, index) in ledger.items" :key="item.id" class="list-item">
            <view class="sale-item">
              <view class="l-info">
                <view class="l-title">{{ item.title || item.reason }}</view>
                <view class="time-and-type">
                  <text v-if="item.createdAt">{{ formatIsoDate(item.createdAt) }}</text>
                  <text v-else>--</text>
                </view>
              </view>
              <view class="r-info">
                <view class="day-num" :class="{ redtext: item.direction === 'debit' }">
                  {{ entryAmount(item) }}
                </view>
                <view class="day-num1">
                  <text :class="item.direction === 'credit' ? 'greentext' : 'redtext'">{{ item.reason }}</text>
                </view>
              </view>
            </view>
            <u-line v-if="index < ledger.items.length - 1" color="#F0F0F0" />
          </view>
        </view>

        <view v-if="ledger.descriptionText" class="points-desc">
          <rich-text :nodes="ledger.descriptionText" />
        </view>
      </view>

      <view class="loadmore-wrap">
        <u-loadmore
          :status="ledger.pagination.page < ledger.pagination.lastPage ? 'loadmore' : 'nomore'"
          :loadmore-text="loadingMore ? '加载中...' : '加载更多'"
          nomore-text="没有更多了哦"
          @loadmore="loadMore"
        />
      </view>

      <bottom-logo />
    </template>
  </view>
</template>

<style scoped lang="scss">
.points-page {
  min-height: 100vh;
  background: #f5f5f5;
}

.points-head {
  padding: 70rpx 0 60rpx;
  background: linear-gradient(180deg, #fef3d8 0%, #fbe7b8 100%);
}

.summarize {
  text-align: center;
}

.summarize .num {
  color: #603401;
  font-size: 88rpx;
  font-weight: 700;
  line-height: 100rpx;
}

.summarize .num-text {
  color: #603401;
  font-size: 26rpx;
}

.setting-wrap {
  margin-top: -20rpx;
  padding: 30rpx 28rpx 20rpx;
  background: #fff;
  border-radius: 24rpx 24rpx 0 0;
  min-height: 200rpx;
}

.list-item {
  padding: 4rpx 0;
}

.sale-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 20rpx 6rpx;
}

.l-info {
  max-width: 420rpx;
}

.l-title {
  color: #181818;
  font-size: 28rpx;
  font-weight: 500;
  line-height: 36rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.time-and-type {
  margin-top: 10rpx;
  color: #989898;
  font-size: 21rpx;
}

.r-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.day-num {
  color: #181818;
  font-size: 32rpx;
  line-height: 32rpx;
}

.day-num.redtext {
  color: #dc3c5c;
}

.day-num1 {
  margin-top: 10rpx;
  font-size: 24rpx;
  text-align: end;
}

.greentext {
  color: #22c788;
}

.redtext {
  color: #dc3c5c;
}

.points-desc {
  margin: 40rpx 10rpx 10rpx;
  padding: 20rpx;
  background: #fef9de;
  border-radius: 15rpx;
  color: #c96a2f;
  font-size: 26rpx;
  line-height: 36rpx;
}

.loadmore-wrap {
  padding: 20rpx 28rpx 0;
}
</style>
