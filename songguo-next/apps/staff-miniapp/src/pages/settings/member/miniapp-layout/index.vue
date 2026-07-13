<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchMemberMiniappLayout, updateMemberMiniappLayout } from "@/api/settings";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { MemberMiniappLayoutItem } from "@/types/settings";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const items = ref<MemberMiniappLayoutItem[]>([]);

const canRead = computed(() => session.can("tenant.member-experience.read"));
const canWrite = computed(() => session.can("tenant.member-experience.write"));

const groupLabels: Record<string, string> = {
  home: "首页",
  course: "课程",
  mine: "我的",
  refuse: "预约拦截",
  other: "其他",
};

const groupedItems = computed(() => {
  const groups = new Map<string, MemberMiniappLayoutItem[]>();
  for (const item of items.value) {
    const list = groups.get(item.group) ?? [];
    list.push(item);
    groups.set(item.group, list);
  }
  return [...groups.entries()].map(([group, groupItems]) => ({
    group,
    label: groupLabels[group] ?? group,
    items: groupItems,
  }));
});

async function load() {
  if (!session.currentSiteId || !canRead.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const config = await fetchMemberMiniappLayout(session.currentSiteId);
    items.value = config.items;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "布局配置加载失败";
  } finally {
    loading.value = false;
  }
}

async function toggleItem(item: MemberMiniappLayoutItem) {
  if (!session.currentSiteId || !canWrite.value) return;
  item.enabled = !item.enabled;
  saving.value = true;
  errorMessage.value = "";
  try {
    const config = await updateMemberMiniappLayout(
      session.currentSiteId,
      items.value.map((entry) => ({ key: entry.key, enabled: entry.enabled })),
    );
    items.value = config.items;
    uni.showToast({ title: "已保存", icon: "none" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
    await load();
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
    <u-empty v-if="!canRead" mode="permission" text="暂无查看权限" />

    <view v-else>
      <view v-for="section in groupedItems" :key="section.group" class="panel">
        <view class="section-title">{{ section.label }}</view>
        <u-cell-group>
          <u-cell v-for="item in section.items" :key="item.key" :title="item.label">
            <template #value>
              <u-switch
                :model-value="item.enabled"
                :disabled="!canWrite"
                @change="toggleItem(item)"
              />
            </template>
          </u-cell>
        </u-cell-group>
      </view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  min-height: 100vh;
  padding: 24rpx;
  background: #f4f6f8;
}

.panel {
  margin-bottom: 24rpx;
  border-radius: 16rpx;
  overflow: hidden;
  background: #fff;
}

.section-title {
  padding: 24rpx 28rpx 8rpx;
  font-size: 28rpx;
  font-weight: 600;
}
</style>
