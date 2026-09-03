<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireStaffAuth } from "@/auth/guard";
import { fetchCrmDeletedMembers, restoreCrmMember } from "@/api/crm";
import { useSessionStore } from "@/stores/session";
import type { CrmDeletedMember } from "@/types/crm";

const session = useSessionStore();
const loading = ref(true);
const loadingMore = ref(false);
const restoringId = ref<number>();
const errorMessage = ref("");
const members = ref<CrmDeletedMember[]>([]);
const page = ref(1);
const lastPage = ref(1);

async function load(reset = true) {
  if (!session.currentSiteId) {
    errorMessage.value = "当前账号没有可用场馆";
    loading.value = false;
    uni.stopPullDownRefresh();
    return;
  }
  if (reset) {
    page.value = 1;
    loading.value = true;
    errorMessage.value = "";
  } else {
    loadingMore.value = true;
  }
  try {
    const requestedPage = reset ? 1 : page.value + 1;
    const response = await fetchCrmDeletedMembers(session.currentSiteId, requestedPage);
    members.value = reset ? response.data.items : [...members.value, ...response.data.items];
    page.value = requestedPage;
    lastPage.value = response.data.pagination.lastPage;
  } catch (error) {
    const message = error instanceof Error ? error.message : "已删除会员加载失败";
    if (reset) errorMessage.value = message;
    else uni.showToast({ title: message, icon: "none" });
  } finally {
    loading.value = false;
    loadingMore.value = false;
    uni.stopPullDownRefresh();
  }
}

async function loadMore() {
  if (loadingMore.value || page.value >= lastPage.value) return;
  await load(false);
}

async function restore(member: CrmDeletedMember) {
  if (!session.currentSiteId || restoringId.value) return;
  restoringId.value = member.id;
  try {
    await restoreCrmMember(session.currentSiteId, member.id);
    uni.showToast({ title: "已恢复", icon: "success" });
    await load();
  } catch (error) {
    const message = error instanceof Error ? error.message : "恢复失败";
    uni.showToast({ title: message, icon: "none" });
  } finally {
    restoringId.value = undefined;
  }
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
onPullDownRefresh(() => load());
</script>

<template>
  <view class="deleted-page">
    <view class="page-banner">
      <text class="banner-title">已删除会员</text>
      <text class="banner-hint">仅支持恢复归档会员，不会执行物理删除</text>
    </view>

    <u-loading-page :loading="loading" />
    <view v-if="!loading" class="list-area">
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <u-empty v-else-if="members.length === 0" mode="list" text="暂无已删除会员" />
      <view v-else>
        <view v-for="member in members" :key="member.id" class="member-row">
          <view class="member-main">
            <view class="name-line">{{ member.name || '未命名会员' }}</view>
            <view class="meta-line">{{ member.mobileMasked || '未留手机号' }} · {{ member.memberNo }}</view>
            <view class="meta-line">删除于 {{ member.archivedAt?.slice(0, 16).replace('T', ' ') }}</view>
          </view>
          <u-button
            v-if="session.can('crm.member.restore')"
            size="mini"
            type="primary"
            plain
            :loading="restoringId === member.id"
            @click="restore(member)"
          >
            恢复
          </u-button>
        </view>
        <u-loadmore :status="page >= lastPage ? 'nomore' : loadingMore ? 'loading' : 'loadmore'" @loadmore="loadMore" />
      </view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.deleted-page { min-height: 100vh; background: $color-page; }
.page-banner { padding: 24rpx; background: $color-surface; border-bottom: 1rpx solid $color-border; }
.banner-title { display: block; font-size: 32rpx; font-weight: 600; }
.banner-hint { display: block; margin-top: 8rpx; color: $color-text-secondary; font-size: 24rpx; }
.list-area { padding: 0 24rpx 48rpx; }
.member-row { display: flex; align-items: center; gap: 20rpx; min-height: 132rpx; border-bottom: 1rpx solid $color-border; }
.member-main { min-width: 0; flex: 1; }
.name-line { font-weight: 600; }
.meta-line { margin-top: 8rpx; color: $color-text-secondary; font-size: 24rpx; }
</style>
