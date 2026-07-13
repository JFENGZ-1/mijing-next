<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchSupportContact, type StaffSupportContact } from "@/api/support";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const contact = ref<StaffSupportContact | null>(null);

const canRead = computed(() => session.can("tenant.settings.support.read"));

async function load() {
  if (!session.currentSiteId || !canRead.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    contact.value = await fetchSupportContact(session.currentSiteId);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "客服信息加载失败";
  } finally {
    loading.value = false;
  }
}

function openLink(url: string) {
  if (!url) return;
  uni.setClipboardData({ data: url, success: () => uni.showToast({ title: "链接已复制", icon: "none" }) });
}

function callPhone(phone: string) {
  uni.makePhoneCall({ phoneNumber: phone.replace(/[^\d+]/g, "") });
}

function callSupportPhone() {
  if (!contact.value) return;
  callPhone(contact.value.phone);
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-empty v-if="!canRead" mode="permission" text="暂无服务中心权限" />
    <template v-else-if="contact">
      <u-alert type="info" :description="contact.supportHint" />
      <view class="card">
        <text class="label">客服电话</text>
        <text class="value" @click="callSupportPhone">{{ contact.phone }}</text>
        <text class="label">微信客服</text>
        <text class="value">{{ contact.wechatId }}</text>
        <text class="label">服务时间</text>
        <text class="value">{{ contact.hours }}</text>
      </view>
      <view class="section-title">常见问题</view>
      <view v-for="link in contact.faqLinks" :key="link.url" class="card row" @click="openLink(link.url)">
        <text>{{ link.title }}</text>
        <u-icon name="arrow-right" color="#98a2b3" />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f4f6f8; }
.card { margin-top: 16rpx; padding: 20rpx; background: #fff; border-radius: 16rpx; }
.row { display: flex; align-items: center; justify-content: space-between; }
.label, .value { display: block; }
.label { margin-top: 12rpx; color: #98a2b3; font-size: 22rpx; }
.value { font-size: 28rpx; }
.section-title { margin: 24rpx 0 8rpx; font-size: 28rpx; font-weight: 600; }
</style>
