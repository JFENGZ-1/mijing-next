<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchAllCompensationRoles } from "@/api/compensation";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CompensationRole } from "@/types/compensation";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const roles = ref<CompensationRole[]>([]);
const canRead = computed(() => session.can("compensation.role.read"));
const canWrite = computed(() => session.can("compensation.role.write"));

const deliveryRoles = computed(() => roles.value.filter((item) => item.type === "delivery"));
const shareRoles = computed(() => roles.value.filter((item) => item.type === "share"));

async function load() {
  if (!session.currentSiteId || !canRead.value) { loading.value = false; return; }
  loading.value = true;
  errorMessage.value = "";
  try {
    roles.value = await fetchAllCompensationRoles(session.currentSiteId);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "业务角色加载失败";
  } finally {
    loading.value = false;
  }
}

function openEdit(role?: CompensationRole) {
  if (!canWrite.value) {
    uni.showToast({ title: "暂无业务角色编辑权限", icon: "none" });
    return;
  }
  uni.navigateTo({
    url: role
      ? `/subpackages/settings/business-roles/edit?id=${role.id}`
      : "/subpackages/settings/business-roles/edit",
  });
}

function openAssignments() {
  if (!canWrite.value) {
    uni.showToast({ title: "暂无业务角色分配权限", icon: "none" });
    return;
  }
  uni.navigateTo({ url: "/subpackages/settings/business-roles/assignments" });
}

function typeLabel(type: CompensationRole["type"]) {
  return type === "delivery" ? "A · 实际上课" : "B · 分成归属";
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(async () => {
  await load();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading && canRead" class="page-container role-page">
    <u-alert
      type="warning"
      description="业务角色只用于上课与耗卡分成，不影响员工的系统权限。一个员工可以同时拥有多个业务角色。"
    />
    <u-alert v-if="errorMessage" class="top-gap" type="error" :description="errorMessage" />

    <view v-if="canWrite" class="actions">
      <button class="primary-btn" @tap="openEdit()">新增业务角色</button>
      <button class="secondary-btn" @tap="openAssignments">员工角色分配</button>
    </view>

    <view v-for="group in [
      { key: 'delivery', title: 'A 类型 · 实际上课者', desc: '承担课程交付，可配置每节课时费和耗卡比例', items: deliveryRoles },
      { key: 'share', title: 'B 类型 · 分成角色', desc: '按会员或售卡归属累计耗卡提成', items: shareRoles },
    ]" :key="group.key" class="role-group">
      <view class="group-head">
        <view>
          <text class="group-title">{{ group.title }}</text>
          <text class="group-desc">{{ group.desc }}</text>
        </view>
        <text class="group-count">{{ group.items.length }} 个</text>
      </view>
      <view v-if="group.items.length" class="role-list">
        <view v-for="role in group.items" :key="role.id" class="role-row" @tap="openEdit(role)">
          <view class="role-main">
            <text class="role-name">{{ role.name }}</text>
            <text class="role-meta">{{ typeLabel(role.type) }} · 版本 {{ role.version }}</text>
          </view>
          <view class="role-side">
            <text class="status" :class="{ off: role.status !== 'active' }">
              {{ role.status === "active" ? "启用" : "停用" }}
            </text>
            <u-icon name="arrow-right" size="15" color="#bfbfbf" />
          </view>
        </view>
      </view>
      <view v-else class="empty-group">暂无此类角色</view>
    </view>
  </view>
  <u-empty v-else-if="!loading" mode="permission" text="暂无业务角色查看权限" />
</template>

<style scoped lang="scss">
.role-page { padding-bottom: 60rpx; }
.top-gap { margin-top: 16rpx; }
.actions { display: flex; gap: 16rpx; margin: 24rpx 0; }
.primary-btn, .secondary-btn { flex: 1; height: 78rpx; margin: 0; border-radius: 39rpx; font-size: 27rpx; line-height: 78rpx; }
.primary-btn { background: $color-brand-yellow; color: $color-text; }
.secondary-btn { background: #fff; color: $color-text; border: 1rpx solid $color-border; }
.primary-btn::after, .secondary-btn::after { border: 0; }
.role-group { margin-top: 20rpx; padding: 28rpx 24rpx; background: #fff; border-radius: $radius-lg; }
.group-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 20rpx; }
.group-title, .group-desc { display: block; }
.group-title { font-size: 30rpx; font-weight: 600; }
.group-desc { margin-top: 8rpx; color: $color-text-tertiary; font-size: 23rpx; line-height: 34rpx; }
.group-count { flex-shrink: 0; color: $color-text-tertiary; font-size: 23rpx; }
.role-list { margin-top: 18rpx; }
.role-row { display: flex; align-items: center; justify-content: space-between; padding: 24rpx 0; border-top: 1rpx solid #f2f2f2; }
.role-main { min-width: 0; }
.role-name, .role-meta { display: block; }
.role-name { font-size: 28rpx; }
.role-meta { margin-top: 6rpx; color: $color-text-tertiary; font-size: 22rpx; }
.role-side { display: flex; align-items: center; gap: 10rpx; }
.status { padding: 4rpx 12rpx; color: #168d61; background: #e8f8f1; border-radius: 999rpx; font-size: 21rpx; }
.status.off { color: $color-text-tertiary; background: #f2f2f2; }
.empty-group { margin-top: 24rpx; padding: 30rpx 0; color: $color-text-disabled; font-size: 24rpx; text-align: center; border-top: 1rpx solid #f2f2f2; }
</style>
