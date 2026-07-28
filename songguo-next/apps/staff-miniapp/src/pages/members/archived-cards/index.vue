<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchArchivedMemberCards } from "@/api/member-cards";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffMemberCardSummary } from "@/types/crm";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const items = ref<StaffMemberCardSummary[]>([]);
const page = ref(1);
const lastPage = ref(1);

const canView = computed(() => session.can("member-card.archive"));

async function load(reset = true) {
  if (!session.currentSiteId || !canView.value) {
    loading.value = false;
    uni.stopPullDownRefresh();
    return;
  }
  loading.value = reset;
  errorMessage.value = "";
  try {
    const requestedPage = reset ? 1 : page.value + 1;
    const response = await fetchArchivedMemberCards(session.currentSiteId, requestedPage);
    items.value = reset ? response.items : [...items.value, ...response.items];
    page.value = requestedPage;
    lastPage.value = response.pagination.lastPage;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "归档卡列表加载失败";
    if (reset) items.value = [];
  } finally {
    loading.value = false;
    uni.stopPullDownRefresh();
  }
}

function openCard(cardId: number) {
  uni.navigateTo({ url: `/pages/members/card-detail?id=${cardId}` });
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(() => load());
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-empty v-if="!canView" mode="permission" text="暂无归档卡查看权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <view class="list-card">
        <view v-for="item in items" :key="item.id" class="row" @tap="openCard(item.id)">
          <view>
            <text class="name">{{ item.name || item.cardNo }}</text>
            <text class="meta">{{ item.cardNo }} · {{ item.status }}</text>
          </view>
        </view>
        <u-empty v-if="!items.length" mode="list" text="暂无归档会员卡" />
        <u-loadmore v-else :status="page >= lastPage ? 'nomore' : 'loadmore'" @loadmore="load(false)" />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f5f5f5; }
.list-card { padding: 20rpx; background: #fff; border-radius: 16rpx; }
.row { padding: 12rpx 0; border-bottom: 1rpx solid #f0f0f0; }
.name, .meta { display: block; }
.meta { margin-top: 6rpx; color: #505050; font-size: 24rpx; }
</style>
