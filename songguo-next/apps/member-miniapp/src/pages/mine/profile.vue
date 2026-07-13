<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberTenantProfile, patchMemberTenantProfile, uploadMemberAvatar } from "@/api/member";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberProfileFieldPolicyItem, MemberTenantProfile } from "@/types/member";

const uploadingAvatar = ref(false);
const errorMessage = ref("");
const tenantProfile = ref<MemberTenantProfile | null>(null);
const form = reactive({
  displayName: "",
  gender: "" as "" | "male" | "female" | "undisclosed",
  birthDate: "",
  heightCm: "",
  weightKg: "",
});

const loading = ref(true);

const saving = ref(false);

const genderOptions = [
  { label: "未设置", value: "" },
  { label: "男", value: "male" },
  { label: "女", value: "female" },
  { label: "不愿透露", value: "undisclosed" },
] as const;

const editableFields = computed(() => {
  const policy = tenantProfile.value?.fieldPolicy.fields ?? [];
  return new Set(policy.filter((item) => item.memberEditable).map((item) => item.key));
});

function fieldPolicy(key: string): MemberProfileFieldPolicyItem | undefined {
  return tenantProfile.value?.fieldPolicy.fields.find((item) => item.key === key);
}

function genderLabel(gender: string | null) {
  if (gender === "male") return "男";
  if (gender === "female") return "女";
  if (gender === "undisclosed") return "不愿透露";
  return "未设置";
}

function applyProfile(data: MemberTenantProfile) {
  tenantProfile.value = data;
  form.displayName = data.profile.displayName ?? "";
  form.gender = data.profile.gender ?? "";
  form.birthDate = data.profile.birthDate ?? "";
  form.heightCm = data.profile.heightCm ?? "";
  form.weightKg = data.profile.weightKg ?? "";
}

async function load() {
  loading.value = true;
  errorMessage.value = "";
  tenantProfile.value = null;

  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) {
      errorMessage.value = "请先选择场馆";
      return;
    }

    const response = await getMemberTenantProfile(tenant.tenantId);
    applyProfile(response.data);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "资料加载失败";
  } finally {
    loading.value = false;
  }
}

function changeGender(index: number) {
  if (!editableFields.value.has("gender")) return;
  form.gender = genderOptions[index]?.value ?? "";
}

function pickGender() {
  if (!editableFields.value.has("gender")) return;
  uni.showActionSheet({
    itemList: genderOptions.map((item) => item.label),
    success: (result) => changeGender(result.tapIndex),
  });
}

function onBirthDateChange(event: { detail: { value: string } }) {
  form.birthDate = event.detail.value;
}

async function save() {
  const tenant = await ensureMemberTenant();
  if (!tenant || !tenantProfile.value?.profile) return;

  saving.value = true;
  errorMessage.value = "";

  try {
    const payload: Parameters<typeof patchMemberTenantProfile>[1] = {
      version: tenantProfile.value.profile.version,
    };
    if (editableFields.value.has("displayName")) payload.displayName = form.displayName.trim();
    if (editableFields.value.has("gender")) payload.gender = form.gender || null;
    if (editableFields.value.has("birthDate")) payload.birthDate = form.birthDate || null;
    if (editableFields.value.has("heightCm")) payload.heightCm = form.heightCm ? Number(form.heightCm) : null;
    if (editableFields.value.has("weightKg")) payload.weightKg = form.weightKg ? Number(form.weightKg) : null;

    const response = await patchMemberTenantProfile(tenant.tenantId, payload);
    applyProfile(response.data);
    uni.showToast({ title: "已保存", icon: "success" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

function openAccountProfile() {
  uni.navigateTo({ url: "/pages/profile/index" });
}

function chooseAvatar() {
  if (!tenantProfile.value || !editableFields.value.has("avatarObjectKey") || uploadingAvatar.value) return;
  uni.chooseImage({
    count: 1,
    sizeType: ["compressed"],
    sourceType: ["album", "camera"],
    success: async (result) => {
      const filePath = result.tempFilePaths[0];
      if (!filePath) return;

      const tenant = await ensureMemberTenant();
      if (!tenant || !tenantProfile.value) return;

      uploadingAvatar.value = true;
      errorMessage.value = "";
      try {
        const uploaded = await uploadMemberAvatar(tenant.tenantId, filePath, tenantProfile.value.profile.version);
        tenantProfile.value = {
          ...tenantProfile.value,
          profile: {
            ...tenantProfile.value.profile,
            avatarObjectKey: uploaded.avatarObjectKey,
            avatarUrl: uploaded.avatarUrl,
            version: uploaded.version,
          },
        };
        uni.showToast({ title: "头像已更新", icon: "success" });
      } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : "头像上传失败";
      } finally {
        uploadingAvatar.value = false;
      }
    },
  });
}

onShow(async () => { if (await requireMemberAuth()) await load(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container page-container--flush">
    <view v-if="uploadingAvatar" class="avatar-upload-mask">
      <u-loading-icon mode="circle" />
    </view>
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <template v-if="tenantProfile">
      <view class="profile-content">
        <view
          class="photo-wrap"
          :class="{ clickable: editableFields.has('avatarObjectKey') }"
          @click="chooseAvatar"
        >
          <view class="photo">
            <u-avatar
              size="70"
              :src="tenantProfile.profile.avatarUrl || undefined"
              :text="(tenantProfile.profile.displayName || '会').slice(0, 1)"
              bg-color="#22c788"
            />
          </view>
          <view v-if="editableFields.has('avatarObjectKey')" class="modifi-photo-wrap">
            <u-icon name="edit-pen" size="14" color="#003d82" />
            <text class="modifi-photo-text">修改头像</text>
          </view>
        </view>

        <view class="form">
          <u-cell-group>
            <u-cell title="手机号" :value="tenantProfile.profile.mobileMasked || '未验证'" />
            <u-cell
              v-if="fieldPolicy('displayName')"
              title="昵称"
              :value="editableFields.has('displayName') ? undefined : tenantProfile.profile.displayName || '未设置'"
            >
              <template v-if="editableFields.has('displayName')" #value>
                <u-input v-model="form.displayName" placeholder="请输入昵称" border="none" input-align="right" />
              </template>
            </u-cell>
            <u-cell
              title="性别"
              :value="editableFields.has('gender') ? undefined : genderLabel(tenantProfile.profile.gender)"
              :is-link="editableFields.has('gender')"
              @click="pickGender"
            >
              <template v-if="editableFields.has('gender')" #value>
                {{ genderLabel(form.gender || null) }}
              </template>
            </u-cell>
            <u-cell
              v-if="fieldPolicy('birthDate')"
              title="生日"
              :value="editableFields.has('birthDate') ? undefined : tenantProfile.profile.birthDate || '未设置'"
            >
              <template v-if="editableFields.has('birthDate')" #value>
                <picker mode="date" :value="form.birthDate" @change="onBirthDateChange">
                  <view class="picker-value">{{ form.birthDate || "选择日期" }}</view>
                </picker>
              </template>
            </u-cell>
            <u-cell
              v-if="fieldPolicy('heightCm')"
              title="身高"
              :value="editableFields.has('heightCm') ? undefined : tenantProfile.profile.heightCm ? `${tenantProfile.profile.heightCm} cm` : '未设置'"
            >
              <template v-if="editableFields.has('heightCm')" #value>
                <u-input v-model="form.heightCm" type="digit" placeholder="cm" border="none" input-align="right" />
              </template>
            </u-cell>
            <u-cell
              v-if="fieldPolicy('weightKg')"
              title="体重"
              :value="editableFields.has('weightKg') ? undefined : tenantProfile.profile.weightKg ? `${tenantProfile.profile.weightKg} kg` : '未设置'"
            >
              <template v-if="editableFields.has('weightKg')" #value>
                <u-input v-model="form.weightKg" type="digit" placeholder="kg" border="none" input-align="right" />
              </template>
            </u-cell>
          </u-cell-group>
        </view>
      </view>

      <view v-if="tenantProfile.registration.missingFields.length" class="missing-hint">
        待完善：{{ tenantProfile.registration.missingFields.join("、") }}
      </view>

      <view class="actions">
        <u-button v-if="editableFields.size > 0" type="primary" :loading="saving" @click="save">保存资料</u-button>
        <u-button plain @click="openAccountProfile">账号资料</u-button>
      </view>

      <bottom-logo />
    </template>
  </view>
</template>

<style scoped lang="scss">
.profile-content {
  padding-bottom: 40rpx;
  background: #fff;
  overflow: hidden;
}

.photo-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin: 0 auto;
  width: 141rpx;
}

.photo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 141rpx;
  height: 141rpx;
  margin-top: 50rpx;
  margin-bottom: 17rpx;
  border-radius: 50%;
  overflow: hidden;
}

.modifi-photo-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
}

.modifi-photo-text {
  margin-left: 8rpx;
  color: #003d82;
  font-size: 22rpx;
}

.photo-wrap.clickable {
  cursor: pointer;
}

.form {
  margin: 50rpx 35rpx 0;
}

.picker-value {
  color: $color-text-secondary;
  font-size: 28rpx;
}

.missing-hint {
  margin: 24rpx 28rpx 0;
  color: #b45309;
  font-size: 24rpx;
}

.actions {
  display: grid;
  gap: 16rpx;
  margin: 32rpx 28rpx 0;
}

.avatar-upload-mask {
  position: fixed;
  inset: 0;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.55);
}
</style>
