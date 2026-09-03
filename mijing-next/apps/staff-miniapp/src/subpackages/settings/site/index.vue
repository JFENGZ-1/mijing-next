<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchSiteProfile, updateSiteProfile } from "@/api/site-profile";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { SiteBusinessHour, SiteProfile } from "@/types/site-profile";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const profile = ref<SiteProfile | null>(null);
const name = ref("");
const phone = ref("");
const address = ref("");
const description = ref("");
const businessHours = ref<SiteBusinessHour[]>([]);

const canRead = computed(() => session.can("site.profile.read"));
const canWrite = computed(() => session.can("site.profile.write"));
const regionLabel = computed(() => {
  const region = profile.value?.region;
  if (!region?.provinceName) return "未设置";
  return [region.provinceName, region.cityName, region.countyName].filter(Boolean).join(" / ");
});

async function load() {
  if (!session.currentSiteId || !canRead.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    profile.value = await fetchSiteProfile(session.currentSiteId);
    name.value = profile.value.name;
    phone.value = profile.value.phone || "";
    address.value = profile.value.address || "";
    description.value = profile.value.description || "";
    businessHours.value = profile.value.businessHours.length
      ? profile.value.businessHours.map((item) => ({ ...item }))
      : [{ weekDays: "1234567", timeValue: "09:00~21:00" }];
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "场馆资料加载失败";
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!profile.value || !session.currentSiteId || !canWrite.value) return;
  saving.value = true;
  errorMessage.value = "";
  try {
    profile.value = await updateSiteProfile(session.currentSiteId, {
      name: name.value.trim(),
      phone: phone.value.trim() || null,
      address: address.value.trim() || null,
      description: description.value.trim() || null,
      businessHours: businessHours.value,
      version: profile.value.version,
    });
    name.value = profile.value.name;
    uni.showToast({ title: "保存成功", icon: "none" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!canRead" mode="permission" text="暂无查看场馆资料权限" />

    <template v-else-if="profile">
      <u-cell-group>
        <u-cell title="当前场馆" :value="profile.name" />
        <u-cell title="所在地区" :value="regionLabel" />
      </u-cell-group>

      <view class="field-block">
        <view class="field-label">场馆名称</view>
        <u-input v-model="name" placeholder="请输入场馆名称" maxlength="120" :disabled="!canWrite" />
      </view>

      <view class="field-block">
        <view class="field-label">联系电话</view>
        <u-input v-model="phone" placeholder="请输入联系电话" maxlength="32" :disabled="!canWrite" />
      </view>

      <view class="field-block">
        <view class="field-label">详细地址</view>
        <u-input v-model="address" placeholder="请输入详细地址" maxlength="255" :disabled="!canWrite" />
      </view>

      <view class="field-block">
        <view class="field-label">场馆简介</view>
        <u-textarea v-model="description" placeholder="请输入场馆简介" maxlength="2000" :disabled="!canWrite" />
      </view>

      <view class="field-block">
        <view class="field-label">营业时间</view>
        <view v-for="(slot, index) in businessHours" :key="index" class="hours-row">
          <u-input v-model="slot.timeValue" placeholder="例如 09:00~21:00" :disabled="!canWrite" />
        </view>
      </view>

      <view v-if="canWrite" class="actions">
        <u-button type="primary" :loading="saving" @click="save">保存场馆资料</u-button>
      </view>
    </template>

    <u-empty v-else mode="data" text="暂无场馆资料" />
  </view>
</template>

<style scoped lang="scss">
.field-block {
  margin-top: 24rpx;
}

.field-label {
  margin-bottom: 12rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.hours-row {
  margin-bottom: 12rpx;
}

.actions {
  margin-top: 32rpx;
}
</style>
