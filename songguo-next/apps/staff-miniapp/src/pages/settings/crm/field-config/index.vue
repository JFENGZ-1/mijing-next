<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchCrmMemberFieldPolicy, updateCrmMemberFieldPolicy } from "@/api/settings";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CrmMemberFieldPolicyItem } from "@/types/settings";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const fields = ref<CrmMemberFieldPolicyItem[]>([]);

const canRead = computed(() => session.can("crm.member.read"));
const canWrite = computed(() => session.can("tenant.crm.field-config.write"));

const selectableFields = computed(() =>
  fields.value.filter((field) => field.key !== "name"),
);

const activeFields = computed(() =>
  fields.value.filter((field) => field.isVisible),
);

async function load() {
  if (!session.currentSiteId || !canRead.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const policy = await fetchCrmMemberFieldPolicy(session.currentSiteId);
    fields.value = policy.fields;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "字段配置加载失败";
  } finally {
    loading.value = false;
  }
}

async function persistField(field: CrmMemberFieldPolicyItem) {
  if (!session.currentSiteId || !canWrite.value) return;
  saving.value = true;
  errorMessage.value = "";
  try {
    const policy = await updateCrmMemberFieldPolicy(session.currentSiteId, [{
      key: field.key,
      isRequired: field.isRequired,
      isVisible: field.isVisible,
      staffEditable: field.staffEditable,
    }]);
    fields.value = policy.fields;
    uni.showToast({ title: "已保存", icon: "none" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
    await load();
  } finally {
    saving.value = false;
  }
}

function toggleVisible(field: CrmMemberFieldPolicyItem) {
  if (!canWrite.value || field.key === "name") return;
  field.isVisible = !field.isVisible;
  if (!field.isVisible) {
    field.isRequired = false;
  }
  if (field.key === "mobile" && field.isVisible) {
    field.isRequired = true;
  }
  void persistField(field);
}

function toggleRequired(field: CrmMemberFieldPolicyItem) {
  if (!canWrite.value || field.key === "name" || field.key === "mobile" || !field.isVisible) return;
  field.isRequired = !field.isRequired;
  void persistField(field);
}

function isSelected(field: CrmMemberFieldPolicyItem) {
  return field.isVisible;
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!canRead" mode="permission" text="暂无查看会员字段权限" />

    <view v-else>
      <view class="hint-card">
        <text>会员注册时要求填写的资料；无实际意义的字段不建议设为必填。</text>
      </view>

      <view class="chip-row">
        <view
          v-for="field in selectableFields"
          :key="field.key"
          class="chip"
          :class="{ active: isSelected(field), disabled: !canWrite }"
          @click="toggleVisible(field)"
        >
          {{ field.label }}
        </view>
      </view>

      <view class="panel">
        <view class="panel-title">已选资料</view>
        <u-cell-group>
          <u-cell
            v-for="field in activeFields"
            :key="field.key"
            :title="field.label"
          >
            <template #value>
              <text v-if="field.isRequired" class="required-tag">必填</text>
              <u-switch
                :model-value="field.isRequired"
                :disabled="!canWrite || field.key === 'name' || field.key === 'mobile'"
                @change="toggleRequired(field)"
              />
            </template>
          </u-cell>
        </u-cell-group>
      </view>

      <u-empty v-if="!canWrite" mode="permission" text="暂无编辑字段配置权限，仅可查看" />
    </view>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  min-height: 100vh;
  padding: 24rpx;
  background: #f4f6f8;
}

.hint-card {
  margin-bottom: 24rpx;
  padding: 24rpx;
  border-radius: 16rpx;
  background: #fff7ed;
  color: #9a3412;
  font-size: 26rpx;
  line-height: 1.6;
}

.chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: 16rpx;
  margin-bottom: 24rpx;
}

.chip {
  padding: 12rpx 24rpx;
  border-radius: 999rpx;
  background: #fff;
  color: #5f6368;
  font-size: 26rpx;
}

.chip.active {
  background: #e8f0fe;
  color: #1a73e8;
}

.chip.disabled {
  opacity: 0.7;
}

.panel {
  background: #fff;
  border-radius: 16rpx;
  overflow: hidden;
}

.panel-title {
  padding: 24rpx 28rpx 8rpx;
  font-size: 28rpx;
  font-weight: 600;
}

.required-tag {
  margin-right: 16rpx;
  color: #d93025;
  font-size: 24rpx;
}
</style>
