<script setup lang="ts">
import { reactive, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { requireStaffAuth } from "@/auth/guard";
import { createCrmMember, fetchCrmMember, updateCrmMember } from "@/api/crm";
import { useSessionStore } from "@/stores/session";

const session = useSessionStore();
const memberId = ref<number>();
const loading = ref(false);
const saving = ref(false);
const loaded = ref(false);
const errorMessage = ref("");
const version = ref<number>();
const form = reactive({
  name: "",
  mobile: "",
  gender: "" as "" | "male" | "female" | "undisclosed",
  birthDate: "",
  assignToMe: true,
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

async function load() {
  if (!memberId.value || !session.currentSiteId) return;
  loading.value = true;
  try {
    const response = await fetchCrmMember(session.currentSiteId, memberId.value);
    form.name = response.data.name;
    form.gender = response.data.gender ?? "";
    form.birthDate = response.data.birthDate ?? "";
    version.value = response.data.version;
    loaded.value = true;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "资料加载失败";
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!session.currentSiteId || !form.name.trim()) {
    errorMessage.value = "请填写会员姓名";
    return;
  }
  saving.value = true;
  errorMessage.value = "";
  try {
    if (memberId.value) {
      await updateCrmMember(session.currentSiteId, memberId.value, {
        version: version.value!,
        name: form.name.trim(),
        gender: form.gender || null,
        birthDate: form.birthDate || null,
        ...(form.mobile.trim() ? { mobile: form.mobile.trim() } : {}),
      });
    } else {
      await createCrmMember(session.currentSiteId, {
        name: form.name.trim(),
        mobile: form.mobile.trim() || null,
        gender: form.gender || null,
        birthDate: form.birthDate || null,
        assignToMe: form.assignToMe,
      });
    }
    uni.navigateBack();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

onLoad((options) => { if (options?.id) memberId.value = Number(options.id); });
onShow(async () => {
  if (await requireStaffAuth() && memberId.value && !loaded.value) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container form-page">
    <view class="page-title">{{ memberId ? '编辑会员资料' : '新增潜客' }}</view>
    <view class="page-hint">员工录入的是当前租户 CRM 资料，不会创建登录账号或覆盖会员本人资料</view>
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <view class="field-label">姓名</view>
    <u-input v-model="form.name" maxlength="80" placeholder="请输入会员姓名" />

    <view class="field-label">{{ memberId ? '新手机号（留空不修改）' : '手机号（选填）' }}</view>
    <u-input v-model="form.mobile" type="text" maxlength="24" placeholder="员工录入号码默认未验证" />
    <view class="field-help">同租户手机号冲突只提示，不会自动合并账户或会员</view>

    <view class="field-label">性别（选填）</view>
    <picker :range="genderOptions.map((item) => item.label)" @change="form.gender = genderOptions[Number($event.detail.value)]?.value || ''">
      <view class="picker-field">{{ genderLabel() }}</view>
    </picker>

    <view class="field-label">生日（选填）</view>
    <picker mode="date" :value="form.birthDate" :end="new Date().toISOString().slice(0, 10)" @change="form.birthDate = String($event.detail.value)">
      <view class="picker-field">{{ form.birthDate || '请选择' }}</view>
    </picker>

    <view v-if="!memberId" class="owner-row">
      <checkbox :checked="form.assignToMe" color="#ed920f" @tap="form.assignToMe = !form.assignToMe" />
      <text>创建后由我负责跟进</text>
    </view>

    <u-button type="primary" :loading="saving" @click="save">保存</u-button>
  </view>
</template>

<style scoped lang="scss">
.form-page { padding-bottom: 48rpx; }
.page-title { font-size: 36rpx; font-weight: 600; }
.page-hint, .field-help { margin-top: 10rpx; color: $color-text-secondary; font-size: 24rpx; line-height: 1.5; }
.field-label { margin: 28rpx 0 12rpx; color: $color-text-secondary; font-size: 24rpx; }
.picker-field { min-height: 80rpx; box-sizing: border-box; padding: 20rpx; background: $color-surface; border: 1rpx solid $color-border; border-radius: $radius-sm; }
.owner-row { display: flex; align-items: center; margin: 28rpx 0; font-size: 26rpx; }
</style>
