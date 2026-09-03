<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import {
  archiveStaffRoom,
  createStaffRoom,
  fetchStaffRoom,
  updateStaffRoom,
} from "@/api/catalog";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { RoomCatalogItem } from "@/types/catalog";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const roomId = ref<number | null>(null);
const room = ref<RoomCatalogItem | null>(null);
const name = ref("");
const capacity = ref("");
const sortOrder = ref("0");

const isEdit = computed(() => roomId.value !== null);
const canRead = computed(() => session.can("site.rooms.read"));
const canWrite = computed(() => session.can("site.rooms.write"));
const isArchived = computed(() => room.value?.catalogStatus === "archived");

function parsePositiveInt(value: string, label: string): number | null {
  const trimmed = value.trim();
  if (!trimmed) return null;
  const amount = Number.parseInt(trimmed, 10);
  if (!Number.isFinite(amount) || amount < 1) {
    uni.showToast({ title: `请输入有效${label}`, icon: "none" });
    return null;
  }
  return amount;
}

function buildCreatePayload() {
  if (!name.value.trim()) {
    uni.showToast({ title: "请填写教室名称", icon: "none" });
    return null;
  }
  const payload: Parameters<typeof createStaffRoom>[1] = {
    name: name.value.trim(),
    sortOrder: Number.parseInt(sortOrder.value, 10) || 0,
  };
  if (capacity.value.trim()) {
    const parsed = parsePositiveInt(capacity.value, "容纳人数");
    if (parsed == null) return null;
    payload.capacity = parsed;
  }
  return payload;
}

function buildUpdatePayload() {
  if (!room.value) return null;
  const base = buildCreatePayload();
  if (!base) return null;
  return { ...base, version: room.value.version ?? 1 };
}

function fillForm(detail: RoomCatalogItem) {
  name.value = detail.name;
  capacity.value = detail.capacity != null ? String(detail.capacity) : "";
  sortOrder.value = String(detail.sortOrder ?? 0);
}

async function load() {
  if (!session.currentSiteId || !canRead.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    if (roomId.value) {
      room.value = await fetchStaffRoom(session.currentSiteId, roomId.value);
      fillForm(room.value);
    } else {
      room.value = null;
      name.value = "";
      capacity.value = "";
      sortOrder.value = "0";
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "教室资料加载失败";
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!session.currentSiteId || !canWrite.value || isArchived.value) return;
  saving.value = true;
  errorMessage.value = "";
  try {
    const siteId = session.currentSiteId;
    if (isEdit.value && roomId.value) {
      const payload = buildUpdatePayload();
      if (!payload) {
        saving.value = false;
        return;
      }
      room.value = await updateStaffRoom(siteId, roomId.value, payload);
      fillForm(room.value);
      uni.showToast({ title: "保存成功", icon: "none" });
      return;
    }

    const payload = buildCreatePayload();
    if (!payload) {
      saving.value = false;
      return;
    }
    const created = await createStaffRoom(siteId, payload);
    uni.showToast({ title: "已创建", icon: "none" });
    uni.redirectTo({ url: `/subpackages/settings/rooms/edit?id=${created.id}` });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

async function archive() {
  if (!session.currentSiteId || !roomId.value || !canWrite.value || isArchived.value) return;
  const confirmed = await new Promise<boolean>((resolve) => {
    uni.showModal({
      title: "确认归档",
      content: "归档后该教室将不再出现在排课与课程关联列表中，且不可物理删除。",
      success: (result) => resolve(Boolean(result.confirm)),
    });
  });
  if (!confirmed) return;

  saving.value = true;
  errorMessage.value = "";
  try {
    room.value = await archiveStaffRoom(session.currentSiteId, roomId.value);
    fillForm(room.value);
    uni.showToast({ title: "已归档", icon: "none" });
    setTimeout(() => uni.navigateBack(), 600);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "归档失败";
  } finally {
    saving.value = false;
  }
}

onLoad((options) => {
  const id = Number(options?.id);
  roomId.value = Number.isFinite(id) && id > 0 ? id : null;
});

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!canRead" mode="permission" text="暂无查看教室权限" />

    <template v-else>
      <u-alert
        v-if="isArchived"
        type="warning"
        description="该教室已归档，不再出现在排课与课程关联列表中。"
      />

      <view class="field-block">
        <view class="field-label">教室名称</view>
        <u-input
          v-model="name"
          placeholder="例如 A教室、瑜伽室"
          maxlength="80"
          :disabled="!canWrite || isArchived"
        />
      </view>

      <view class="field-block">
        <view class="field-label">容纳人数（可选）</view>
        <u-input
          v-model="capacity"
          type="number"
          placeholder="例如 15"
          :disabled="!canWrite || isArchived"
        />
      </view>

      <view class="field-block">
        <view class="field-label">排序</view>
        <u-input
          v-model="sortOrder"
          type="number"
          placeholder="数字越小越靠前"
          :disabled="!canWrite || isArchived"
        />
      </view>

      <view v-if="canWrite && !isArchived" class="actions">
        <u-button type="primary" :loading="saving" @click="save">
          {{ isEdit ? "保存教室" : "创建教室" }}
        </u-button>
        <u-button
          v-if="isEdit"
          type="error"
          plain
          :loading="saving"
          text="归档教室"
          @click="archive"
        />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  padding: 24rpx;
}

.field-block {
  margin-bottom: 24rpx;
}

.field-label {
  margin-bottom: 12rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.actions {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
  margin-top: 32rpx;
}
</style>
