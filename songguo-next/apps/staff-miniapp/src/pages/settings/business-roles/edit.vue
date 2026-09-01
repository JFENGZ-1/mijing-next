<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import {
  archiveCompensationRole,
  createCompensationRole,
  fetchCompensationRole,
  updateCompensationRole,
} from "@/api/compensation";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CompensationRoleType } from "@/types/compensation";
import { createCommandKey } from "@/utils/command-key";

const session = useSessionStore();
const roleId = ref(0);
const loading = ref(false);
const submitting = ref(false);
const form = reactive({
  name: "",
  type: "delivery" as CompensationRoleType,
  status: "active" as "active" | "inactive",
  version: 0,
});

const isEdit = computed(() => roleId.value > 0);
const canWrite = computed(() => session.can("compensation.role.write"));
const typeOptions = [
  { value: "delivery" as const, label: "A 类型 · 实际上课者", desc: "承担课程交付，可获得课时费与耗卡提成" },
  { value: "share" as const, label: "B 类型 · 分成角色", desc: "不要求实际上课，按归属耗卡累计提成" },
];

async function load() {
  if (!isEdit.value || !session.currentSiteId) return;
  loading.value = true;
  try {
    const role = await fetchCompensationRole(session.currentSiteId, roleId.value);
    form.name = role.name;
    form.type = role.type;
    form.status = role.status === "active" ? "active" : "inactive";
    form.version = role.version;
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "角色加载失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}

async function submit() {
  if (!session.currentSiteId || submitting.value || !canWrite.value) return;
  const name = form.name.trim();
  if (name.length < 2) {
    uni.showToast({ title: "角色名称至少 2 个字符", icon: "none" });
    return;
  }
  submitting.value = true;
  try {
    if (isEdit.value) {
      await updateCompensationRole(session.currentSiteId, roleId.value, {
        name,
        type: form.type,
        status: form.status,
        version: form.version,
        commandKey: createCommandKey(),
        reason: "员工端更新业务角色",
      });
    } else {
      await createCompensationRole(session.currentSiteId, {
        name,
        type: form.type,
        status: form.status,
        commandKey: createCommandKey(),
        reason: "员工端创建业务角色",
      });
    }
    uni.showToast({ title: "保存成功", icon: "success" });
    setTimeout(() => uni.navigateBack(), 350);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "保存失败", icon: "none" });
  } finally {
    submitting.value = false;
  }
}

function remove() {
  if (!session.currentSiteId || !isEdit.value) return;
  uni.showModal({
    title: "归档业务角色",
    content: "归档后不能再用于新规则或新分配，历史结算与角色快照会完整保留。",
    success: async (result) => {
      if (!result.confirm || !session.currentSiteId) return;
      try {
        await archiveCompensationRole(session.currentSiteId, roleId.value, {
          version: form.version,
          reason: "员工端归档业务角色",
          commandKey: createCommandKey(),
        });
        uni.showToast({ title: "已归档", icon: "success" });
        setTimeout(() => uni.navigateBack(), 350);
      } catch (error) {
        uni.showToast({ title: error instanceof Error ? error.message : "归档失败", icon: "none" });
      }
    },
  });
}

onLoad((options) => {
  roleId.value = Number(options?.id || 0);
});

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading && canWrite" class="page-container edit-page">
    <view class="form-card">
      <view class="field">
        <text class="label">角色名称</text>
        <u-input v-model="form.name" maxlength="30" placeholder="例如：主教练、会籍顾问" border="surround" />
      </view>
      <view class="field">
        <text class="label">业务类型</text>
        <view
          v-for="option in typeOptions"
          :key="option.value"
          class="type-option"
          :class="{ active: form.type === option.value, disabled: isEdit }"
          @tap="!isEdit && (form.type = option.value)"
        >
          <view>
            <text class="option-title">{{ option.label }}</text>
            <text class="option-desc">{{ option.desc }}</text>
          </view>
          <u-icon :name="form.type === option.value ? 'checkmark-circle-fill' : 'checkmark-circle'" :color="form.type === option.value ? '#22c788' : '#dadada'" size="22" />
        </view>
        <text v-if="isEdit" class="immutable-hint">角色类型创建后不可更改，避免历史结算归属发生歧义。</text>
      </view>
      <view v-if="isEdit" class="version-row">当前版本 {{ form.version }}，保存时由后端进行并发校验</view>
    </view>

    <button class="save-btn" :disabled="submitting" @tap="submit">{{ submitting ? "保存中…" : "保存" }}</button>
    <button v-if="isEdit && form.status === 'active'" class="delete-btn" @tap="remove">归档角色</button>
  </view>
  <u-empty v-else-if="!loading" mode="permission" text="暂无业务角色编辑权限" />
</template>

<style scoped lang="scss">
.edit-page { padding-bottom: 60rpx; }
.form-card { padding: 30rpx 26rpx; background: #fff; border-radius: $radius-lg; }
.field + .field { margin-top: 34rpx; }
.label { display: block; margin-bottom: 14rpx; font-size: 28rpx; font-weight: 600; }
.type-option { display: flex; align-items: center; justify-content: space-between; gap: 20rpx; margin-top: 14rpx; padding: 24rpx 20rpx; border: 1rpx solid $color-border; border-radius: 14rpx; }
.type-option.active { border-color: $color-brand-yellow; background: #fffbed; }
.type-option.disabled { opacity: .72; }
.immutable-hint { display: block; margin-top: 12rpx; color: $color-text-tertiary; font-size: 21rpx; line-height: 32rpx; }
.option-title, .option-desc { display: block; }
.option-title { font-size: 27rpx; }
.option-desc { margin-top: 7rpx; color: $color-text-tertiary; font-size: 22rpx; line-height: 32rpx; }
.switch-row { display: flex; align-items: center; justify-content: space-between; margin-top: 34rpx; padding-top: 26rpx; border-top: 1rpx solid #f0f0f0; }
.switch-row .label { margin-bottom: 4rpx; }
.switch-desc { color: $color-text-tertiary; font-size: 22rpx; }
.version-row { margin-top: 26rpx; color: $color-text-tertiary; font-size: 22rpx; }
.save-btn, .delete-btn { width: 480rpx; height: 82rpx; margin: 42rpx auto 0; border-radius: 41rpx; font-size: 30rpx; line-height: 82rpx; }
.save-btn { background: $color-brand-yellow; color: $color-text; }
.delete-btn { margin-top: 20rpx; color: $color-danger; background: #fff; border: 1rpx solid #f0c6cf; }
.save-btn::after, .delete-btn::after { border: 0; }
</style>
