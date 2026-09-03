<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchPointsConfig, updatePointsConfig } from "@/api/points";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");

const pointsEnabled = ref(false);
const earnPerVisit = ref("");
const earnPerPurchase = ref("");
const debitEnabled = ref(false);
const descriptionText = ref("");

const canWrite = computed(() => session.can("points.config.write"));

async function load() {
  if (!session.currentSiteId) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const config = await fetchPointsConfig(session.currentSiteId);
    pointsEnabled.value = !!config.pointsEnabled;
    earnPerVisit.value = String(config.policy?.earnPerVisit ?? 0);
    earnPerPurchase.value = String(config.policy?.earnPerPurchase ?? 0);
    debitEnabled.value = !!config.policy?.debitEnabled;
    descriptionText.value = config.descriptionText ?? "";
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "积分配置加载失败";
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!session.currentSiteId || !canWrite.value) return;
  const visit = Number.parseInt(earnPerVisit.value || "0", 10);
  const purchase = Number.parseInt(earnPerPurchase.value || "0", 10);
  if (visit < 0 || purchase < 0 || Number.isNaN(visit) || Number.isNaN(purchase)) {
    uni.showToast({ title: "积分值需为非负整数", icon: "none" });
    return;
  }
  saving.value = true;
  try {
    await updatePointsConfig(session.currentSiteId, {
      pointsEnabled: pointsEnabled.value,
      descriptionText: descriptionText.value.trim() || null,
      policy: {
        earnPerVisit: visit,
        earnPerPurchase: purchase,
        debitEnabled: debitEnabled.value,
      },
    });
    uni.showToast({ title: "已保存", icon: "success" });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "保存失败", icon: "none" });
  } finally {
    saving.value = false;
  }
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <view class="sg-card">
      <u-cell-group>
        <u-cell title="启用积分体系">
          <template #value>
            <u-switch v-model="pointsEnabled" :disabled="!canWrite" active-color="#ed920f" />
          </template>
        </u-cell>
        <u-cell title="允许扣减积分">
          <template #value>
            <u-switch v-model="debitEnabled" :disabled="!canWrite || !pointsEnabled" active-color="#ed920f" />
          </template>
        </u-cell>
      </u-cell-group>
    </view>

    <view class="sg-card block-card">
      <text class="card-title">获取规则</text>
      <view class="field-row">
        <text class="field-label">每次上课得分</text>
        <u-input v-model="earnPerVisit" type="number" placeholder="0" :disabled="!canWrite || !pointsEnabled" border="bottom" />
      </view>
      <view class="field-row">
        <text class="field-label">每次购卡得分</text>
        <u-input v-model="earnPerPurchase" type="number" placeholder="0" :disabled="!canWrite || !pointsEnabled" border="bottom" />
      </view>
    </view>

    <view class="sg-card block-card">
      <text class="card-title">积分说明（会员端展示）</text>
      <u-textarea
        v-model="descriptionText"
        maxlength="2000"
        placeholder="向会员说明积分的获取与用途"
        :disabled="!canWrite"
      />
    </view>

    <button v-if="canWrite" class="sg-btn-primary save-btn" :disabled="saving" @click="save">
      {{ saving ? "保存中..." : "保存配置" }}
    </button>
  </view>
</template>

<style scoped lang="scss">
.block-card {
  margin-top: $spacing-md;
}

.card-title {
  display: block;
  margin-bottom: 12rpx;
  font-size: 30rpx;
  font-weight: 600;
  color: $color-text;
}

.field-row {
  display: flex;
  align-items: center;
  gap: 24rpx;
  padding: 12rpx 0;
}

.field-label {
  flex-shrink: 0;
  width: 220rpx;
  color: $color-text-secondary;
  font-size: 28rpx;
}

.save-btn {
  margin-top: 48rpx;
  border: none;
}

.save-btn::after {
  border: 0;
}
</style>
