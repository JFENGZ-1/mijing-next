<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { useApiClient } from "@/api/client";
import { ApiError } from "@mijing/api-client";
import { useSessionStore } from "@/stores/session";
import type { LegalDocumentData, MemberLinkReview, MemberMembershipResult, MemberOnboardingData, MemberSiteOption } from "@/types/member";
import { selectMemberSite } from "@/composables/member-context";

const session = useSessionStore();
const saving = ref(false);
const phoneLoading = ref(false);
const errorMessage = ref("");
const phoneError = ref("");
const documents = ref<LegalDocumentData[]>([]);
const sites = ref<MemberSiteOption[]>([]);
const acceptedDocumentIds = ref<number[]>([]);
const selectedSiteId = ref<number | undefined>();
const activeDocument = ref<LegalDocumentData | null>(null);
const profileVersion = ref<number | undefined>();
const mobileVerified = ref(false);
const mobileMasked = ref("");
const form = reactive({
  displayName: "",
  gender: "" as "" | "male" | "female" | "undisclosed",
  birthDate: "",
  heightCm: "",
  weightKg: "",
});
const genderOptions = [
  { label: "未设置", value: "" },
  { label: "男", value: "male" },
  { label: "女", value: "female" },
  { label: "不愿透露", value: "undisclosed" },
] as const;

function genderLabel() {
  return genderOptions.find((item) => item.value === form.gender)?.label ?? "未设置";
}

function changeGender(index: number) {
  form.gender = genderOptions[index]?.value ?? "";
}

const allDocumentsAccepted = computed(
  () => documents.value.length > 0 && documents.value.every((item) => acceptedDocumentIds.value.includes(item.id)),
);
const selectedSite = computed(() => sites.value.find((site) => site.id === selectedSiteId.value));

const loading = ref(true);

function applyOnboarding(data: MemberOnboardingData) {
  session.setRegistrationState(data.state);
  profileVersion.value = data.profile?.version;
  mobileVerified.value = data.profile?.mobileVerified ?? false;
  mobileMasked.value = data.profile?.mobileMasked ?? "";
  form.displayName = data.profile?.displayName ?? form.displayName;
  form.gender = data.profile?.gender ?? "";
  form.birthDate = data.profile?.birthDate ?? "";
  form.heightCm = data.profile?.heightCm ?? "";
  form.weightKg = data.profile?.weightKg ?? "";
  acceptedDocumentIds.value = [...data.acceptedDocumentIds];
}

const hasLoaded = ref(false);

async function load() {
  // 仅首次显示全屏加载，返回本页时静默刷新
  loading.value = !hasLoaded.value;
  errorMessage.value = "";
  try {
    const [onboarding, legal, siteOptions] = await Promise.all([
      useApiClient().request<MemberOnboardingData>("/member/onboarding"),
      useApiClient().request<LegalDocumentData[]>("/member/legal-documents"),
      useApiClient().request<MemberSiteOption[]>("/member/sites"),
    ]);
    applyOnboarding(onboarding.data);
    documents.value = legal.data;
    sites.value = siteOptions.data;
    if (!onboarding.data.legalConfigurationReady) errorMessage.value = "隐私政策尚未配置，暂时不能完成注册";
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "资料加载失败";
  } finally {
    loading.value = false;
    hasLoaded.value = true;
  }
}

function toggleDocument(id: number) {
  acceptedDocumentIds.value = acceptedDocumentIds.value.includes(id)
    ? acceptedDocumentIds.value.filter((item) => item !== id)
    : [...acceptedDocumentIds.value, id];
}

async function verifyMobile(event: { detail: { code?: string; errMsg?: string } }) {
  const code = event.detail.code;
  if (!code) {
    phoneError.value = event.detail.errMsg?.includes("deny") ? "你已拒绝手机号授权，可点击按钮重新授权" : "未取得手机号凭证";
    return;
  }
  phoneLoading.value = true;
  phoneError.value = "";
  try {
    const response = await useApiClient().request<MemberOnboardingData>("/member/profile/verify-mobile", {
      method: "POST",
      data: { code, ...(profileVersion.value ? { version: profileVersion.value } : {}) },
    });
    applyOnboarding(response.data);
  } catch (error) {
    phoneError.value = error instanceof Error ? error.message : "手机号验证失败";
  } finally {
    phoneLoading.value = false;
  }
}

async function save() {
  errorMessage.value = "";
  if (!form.displayName.trim()) {
    errorMessage.value = "请填写称呼";
    return;
  }
  if (!allDocumentsAccepted.value) {
    errorMessage.value = "请阅读并同意当前隐私政策";
    return;
  }

  saving.value = true;
  try {
    const response = await useApiClient().request<MemberOnboardingData>("/member/profile", {
      method: "PUT",
      data: {
        displayName: form.displayName.trim(),
        gender: form.gender || null,
        birthDate: form.birthDate || null,
        heightCm: form.heightCm || null,
        weightKg: form.weightKg || null,
        acceptedDocumentIds: acceptedDocumentIds.value,
        ...(profileVersion.value ? { version: profileVersion.value } : {}),
      },
    });
    applyOnboarding(response.data);
    if (selectedSiteId.value) {
      const membership = await useApiClient().request<MemberMembershipResult>("/member/memberships", {
        method: "POST",
        data: { siteId: selectedSiteId.value },
      });
      if (membership.data.state === "joined") {
        const site = sites.value.find((item) => item.id === selectedSiteId.value);
        if (site) selectMemberSite(site);
      }
      if (membership.data.state === "link_review") await handleLinkReview(membership.data);
    }
    if (response.data.state === "complete") {
      uni.reLaunch({ url: "/pages/index/index" });
    }
  } catch (error) {
    if (error instanceof ApiError && error.payload.code === "MEMBER_APP_ACCESS_BLOCKED") {
      errorMessage.value = `${selectedSite.value?.name || "当前场馆"}已限制该会员关系访问${selectedSite.value?.phone ? `，请联系${selectedSite.value.phone}` : "，请联系场馆处理"}。你仍可选择其他场馆或使用全局资料功能。`;
    } else if (error instanceof ApiError && error.payload.code === "MEMBER_RELATIONSHIP_CLOSED") {
      errorMessage.value = `${selectedSite.value?.name || "当前场馆"}的会员关系已关闭，请联系场馆恢复后重试。`;
    } else {
      errorMessage.value = error instanceof Error ? error.message : "保存失败";
    }
  } finally {
    saving.value = false;
  }
}

async function handleLinkReview(link: MemberLinkReview) {
  if (link.status === "pending_member_confirmation") {
    const result = await uni.showModal({
      title: "确认场馆档案",
      content: `${link.site.name}已有手机号为${link.candidate.mobileMasked || "同号"}的档案${link.candidate.nameMasked ? `（${link.candidate.nameMasked}）` : ""}。这是你的档案吗？`,
      confirmText: "是我的",
      cancelText: "不是我",
    });
    await useApiClient().request<MemberLinkReview>(`/member/member-link-requests/${link.requestId}/decision`, {
      method: "POST",
      data: { version: link.version, decision: result.confirm ? "link" : "not_me" },
    });
    await uni.showModal({
      title: "已提交审核",
      content: "场馆员工确认后将完成关联或创建独立会员档案。",
      showCancel: false,
    });
    return;
  }
  if (link.status === "pending_staff_review") {
    uni.showToast({ title: "档案关联正在等待场馆审核", icon: "none" });
    return;
  }
  if (link.status === "rejected" || link.status === "conflict") {
    await uni.showModal({
      title: "暂时无法加入",
      content: link.status === "rejected" ? "场馆暂未通过身份审核，请联系场馆处理。" : "发现重复会员档案，需要场馆人工处理。",
      showCancel: false,
    });
  }
}

onShow(async () => { if (await requireMemberAuth({ allowIncomplete: true })) await load(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container onboarding-page">
    <view class="page-title-lg">完善会员资料</view>
    <view class="page-subtitle">手机号可跳过；支付、领卡等关键业务需要时会再次请求授权</view>

    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <view class="form-sheet">
      <view class="section-title section-title--inline">基本资料</view>
      <view class="field-label">称呼</view>
      <input v-model="form.displayName" class="native-input" type="nickname" maxlength="80" placeholder="请输入称呼" />

      <view class="field-label">性别（选填）</view>
      <picker :range="genderOptions.map((item) => item.label)" @change="changeGender(Number($event.detail.value))">
        <view class="picker-field">{{ genderLabel() }}</view>
      </picker>

      <view class="field-label">生日（选填）</view>
      <picker mode="date" :value="form.birthDate" :end="new Date().toISOString().slice(0, 10)" @change="form.birthDate = String($event.detail.value)">
        <view class="picker-field">{{ form.birthDate || '请选择' }}</view>
      </picker>

      <view class="measure-grid">
        <view>
          <view class="field-label">身高 cm（选填）</view>
          <u-input v-model="form.heightCm" type="number" placeholder="未填写" />
        </view>
        <view>
          <view class="field-label">体重 kg（选填）</view>
          <u-input v-model="form.weightKg" type="number" placeholder="未填写" />
        </view>
      </view>
    </view>

    <view class="form-sheet">
      <view class="section-title section-title--inline">手机号验证（选填）</view>
      <view v-if="mobileVerified" class="verified-row">
        <u-icon name="checkmark-circle-fill" color="#22c788" size="20" />
        <text>已验证 {{ mobileMasked }}</text>
      </view>
      <button v-else class="wechat-phone-button" open-type="getPhoneNumber" :disabled="phoneLoading" @getphonenumber="verifyMobile">
        {{ phoneLoading ? '验证中...' : '使用微信手机号验证（选填）' }}
      </button>
      <view v-if="phoneError" class="field-error">{{ phoneError }}</view>
    </view>

    <view class="form-sheet">
      <view class="section-title section-title--inline">隐私与协议</view>
      <view v-for="document in documents" :key="document.id" class="consent-row">
        <checkbox :checked="acceptedDocumentIds.includes(document.id)" color="#22c788" @tap="toggleDocument(document.id)" />
        <text>我已阅读并同意</text>
        <text class="document-link" @tap.stop="activeDocument = document">《{{ document.title }}》</text>
      </view>
      <u-empty v-if="documents.length === 0" mode="data" text="协议尚未配置" />
    </view>

    <view v-if="sites.length" class="form-sheet">
      <view class="section-title section-title--inline">加入场馆（选填）</view>
      <picker :range="['暂不选择', ...sites.map((site) => site.name)]" @change="selectedSiteId = Number($event.detail.value) > 0 ? sites[Number($event.detail.value) - 1].id : undefined">
        <view class="picker-field">{{ sites.find((site) => site.id === selectedSiteId)?.name || '暂不选择' }}</view>
      </picker>
    </view>

    <button class="save-btn" :loading="saving" :disabled="documents.length === 0" @tap="save">保存并继续</button>

    <view v-if="activeDocument" class="legal-overlay" @tap="activeDocument = null">
      <view class="legal-panel card-sheet" @tap.stop>
        <view class="legal-title">{{ activeDocument.title }}</view>
        <scroll-view scroll-y class="legal-content">{{ activeDocument.content }}</scroll-view>
        <u-button plain @click="activeDocument = null">关闭</u-button>
      </view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.onboarding-page {
  padding-bottom: 48rpx;
  background: $color-page;
}

.measure-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20rpx;
}

.verified-row {
  display: flex;
  align-items: center;
  gap: 12rpx;
  color: $color-primary-dark;
}

.wechat-phone-button {
  height: 84rpx;
  color: #fff;
  line-height: 84rpx;
  background: $color-primary-dark;
  border-radius: $radius-sm;
  font-size: 28rpx;
}

.wechat-phone-button::after {
  border: 0;
}

.field-error {
  margin-top: 12rpx;
  color: $color-accent-pink;
  font-size: 24rpx;
}

.consent-row {
  display: flex;
  align-items: center;
  min-height: 64rpx;
  font-size: 26rpx;
}

.document-link {
  color: $color-primary;
}

.save-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 458rpx;
  height: 90rpx;
  margin: 40rpx auto 0;
  color: $color-text;
  font-size: 34rpx;
  background: $color-accent-yellow;
  border: none;
  border-radius: 50rpx;
}

.save-btn::after {
  border: none;
}

.legal-overlay {
  position: fixed;
  z-index: 20;
  inset: 0;
  display: flex;
  align-items: flex-end;
  background: rgba(0, 0, 0, 0.45);
}

.legal-panel {
  width: 100%;
  max-height: 78vh;
  box-sizing: border-box;
  padding: 28rpx;
  background: #fff;
  border-top-left-radius: $radius-xl;
  border-top-right-radius: $radius-xl;
}

.legal-title {
  margin-bottom: 20rpx;
  font-size: 34rpx;
  font-weight: 600;
}

.legal-content {
  height: 52vh;
  margin-bottom: 24rpx;
  white-space: pre-wrap;
  font-size: 26rpx;
  line-height: 1.7;
}
</style>
