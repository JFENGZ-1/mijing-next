<script setup lang="ts">
import { ref } from "vue";
import { onLoad } from "@dcloudio/uni-app";
import {
  acceptStaffInvite,
  fetchStaffInvitePreview,
  loginForInvite,
  resolveStaffWechatUnionId,
  type StaffInvitePreview,
} from "@/api/staff-invite";
import { useSessionStore } from "@/stores/session";
import type { StaffSiteContext } from "@/stores/session";

const session = useSessionStore();
const loading = ref(true);
const accepting = ref(false);
const errorMessage = ref("");
const sign = ref("");
const preview = ref<StaffInvitePreview | null>(null);

async function loadPreview(wxCode?: string) {
  if (!sign.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    preview.value = await fetchStaffInvitePreview(sign.value, wxCode);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "邀请信息加载失败";
  } finally {
    loading.value = false;
  }
}

async function ensureInviteSession(wxCode: string) {
  const login = await loginForInvite(sign.value, wxCode);
  session.setSession({
    accessToken: login.accessToken,
    tenantId: login.staff?.tenantId ?? 0,
    permissions: login.staff?.permissions ?? [],
    sites: (login.staff?.sites ?? []) as StaffSiteContext[],
  });
  await resolveStaffWechatUnionId(wxCode);
}

async function acceptInvite(event: { detail?: { code?: string } }) {
  const phoneCode = event.detail?.code;
  if (!phoneCode || !sign.value) {
    uni.showToast({ title: "需要授权手机号", icon: "none" });
    return;
  }
  accepting.value = true;
  errorMessage.value = "";
  try {
    const loginResult = await uni.login({ provider: "weixin" });
    await ensureInviteSession(loginResult.code);
    const result = await acceptStaffInvite(sign.value, {
      code: loginResult.code,
      phoneCode,
    });
    session.setSession({
      accessToken: session.accessToken,
      tenantId: result.staff.tenantId,
      permissions: result.staff.permissions,
      sites: result.staff.sites as StaffSiteContext[],
    });
    session.selectSite(result.siteId);
    uni.showToast({ title: "加入成功", icon: "none" });
    setTimeout(() => uni.reLaunch({ url: "/pages/index/index" }), 800);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "接受邀请失败";
  } finally {
    accepting.value = false;
  }
}

onLoad(async (query) => {
  sign.value = String(query?.sign || "");
  const loginResult = await uni.login({ provider: "weixin" });
  await loadPreview(loginResult.code);
});
</script>

<template>
  <u-loading-page :loading="loading || accepting" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <template v-if="preview">
      <view class="hero">
        <text class="site-name">{{ preview.site.name }}</text>
        <text class="headline">邀请你加入团队</text>
        <text class="invitee">{{ preview.invitee.displayName }}</text>
        <text v-if="preview.invitee.role" class="meta">角色：{{ preview.invitee.role.name }}</text>
      </view>

      <button
        v-if="preview.status === 'pending'"
        class="phone-button"
        open-type="getPhoneNumber"
        @getphonenumber="acceptInvite"
      >
        接受邀请并授权手机号
      </button>
      <u-empty v-else mode="data" text="该邀请已失效" />
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  padding: 48rpx 32rpx;
}

.hero {
  margin-bottom: 48rpx;
  text-align: center;
}

.site-name {
  display: block;
  margin-bottom: 16rpx;
  color: #ed920f;
  font-size: 28rpx;
}

.headline {
  display: block;
  margin-bottom: 12rpx;
  font-size: 40rpx;
  font-weight: 600;
}

.invitee {
  display: block;
  font-size: 32rpx;
}

.meta {
  display: block;
  margin-top: 12rpx;
  color: #989898;
  font-size: 26rpx;
}

.phone-button {
  width: 100%;
  padding: 24rpx;
  border: none;
  border-radius: 12rpx;
  background: #ed920f;
  color: #fff;
  font-size: 30rpx;
}
</style>
