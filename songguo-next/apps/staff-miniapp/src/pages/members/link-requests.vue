<script setup lang="ts">
import { reactive, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { requireStaffAuth } from "@/auth/guard";
import { fetchMemberLinkRequests, reviewMemberLinkRequest } from "@/api/crm";
import { useSessionStore } from "@/stores/session";
import type { MemberLinkReview } from "@/types/crm";

const session = useSessionStore();
const requests = ref<MemberLinkReview[]>([]);
const reasons = reactive<Record<string, string>>({});
const loading = ref(true);
const actionId = ref("");
const errorMessage = ref("");

async function load() {
  if (!session.currentSiteId) {
    errorMessage.value = "请先选择场馆";
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await fetchMemberLinkRequests(session.currentSiteId);
    requests.value = response.data;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "审核列表加载失败";
  } finally {
    loading.value = false;
  }
}

async function decide(item: MemberLinkReview, decision: "approve_link" | "approve_separate" | "reject") {
  const reason = reasons[item.requestId]?.trim();
  if (!reason) {
    uni.showToast({ title: "请填写审核原因", icon: "none" });
    return;
  }
  const labels = {
    approve_link: "关联到原潜客档案",
    approve_separate: "创建独立会员档案",
    reject: "拒绝本次加入",
  };
  const confirmation = await uni.showModal({
    title: "确认审核结果",
    content: `${labels[decision]}？该操作会记录审计证据。`,
  });
  if (!confirmation.confirm) return;

  if (!session.currentSiteId) return;

  actionId.value = item.requestId;
  try {
    await reviewMemberLinkRequest(session.currentSiteId, item.requestId, {
      version: item.version,
      decision,
      reason,
    });
    uni.showToast({ title: "审核已提交", icon: "success" });
    await load();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "审核失败", icon: "none" });
  } finally {
    actionId.value = "";
  }
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container review-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-else-if="requests.length === 0" mode="list" text="当前没有待审核的档案关联" />
    <view v-else>
    <view v-for="item in requests" :key="item.requestId" class="review-item">
      <view class="review-heading">
        <view>
          <text class="lead-name">{{ item.leadMember.name }}</text>
          <text class="meta">{{ item.leadMember.mobileMasked || '潜客未留手机号' }} · {{ item.leadMember.memberNo }}</text>
        </view>
        <u-tag :text="item.memberDecision === 'link' ? '会员确认是本人' : '会员声明不是本人'" :type="item.memberDecision === 'link' ? 'warning' : 'info'" size="mini" plain />
      </view>
      <view class="comparison">
        <text>登录账号：{{ item.account.displayName || '未填写称呼' }}</text>
        <text>验证手机号：{{ item.account.mobileMasked || '无' }}</text>
        <text>申请有效期至：{{ item.expiresAt.slice(0, 16).replace('T', ' ') }}</text>
        <text v-if="item.leadMember.appAccessStatus === 'blocked'" class="risk-text">原潜客档案已限制会员端访问，关联后仍会保持限制</text>
        <text v-if="item.leadMember.status === 'closed'" class="risk-text">原潜客关系已关闭，关联后仍不可使用会员业务</text>
      </view>
      <u-input v-model="reasons[item.requestId]" maxlength="500" placeholder="填写审核依据或拒绝原因" />
      <view class="command-grid">
        <u-button
          v-if="item.memberDecision === 'link'"
          type="primary"
          :loading="actionId === item.requestId"
          @click="decide(item, 'approve_link')"
        >关联原档案</u-button>
        <u-button plain :loading="actionId === item.requestId" @click="decide(item, 'approve_separate')">创建独立档案</u-button>
        <u-button type="error" plain :loading="actionId === item.requestId" @click="decide(item, 'reject')">拒绝加入</u-button>
      </view>
    </view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.review-page { padding-bottom: 48rpx; }
.review-item { padding: 28rpx 0; border-bottom: 1rpx solid $color-border; }
.review-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16rpx; }
.lead-name, .meta, .comparison text { display: block; }
.lead-name { font-size: 32rpx; font-weight: 600; }
.meta, .comparison { margin-top: 8rpx; color: $color-text-secondary; font-size: 24rpx; }
.comparison { display: grid; gap: 8rpx; margin-bottom: 20rpx; padding: 20rpx 0; }
.risk-text { color: $color-danger; }
.command-grid { display: grid; gap: 12rpx; margin-top: 18rpx; }
</style>
