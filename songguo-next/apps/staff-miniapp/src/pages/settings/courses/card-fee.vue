<script setup lang="ts">
// 设置课时费 —— 对标原版 pagesImp/subject/suject-choice-card
// 哪些卡可约此课（开关）+ 每节课扣费（储值卡=金额 / 次卡=次数 / 期限卡=扣除期限弹窗）
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { fetchCardProduct, fetchCardProducts, updateCardProduct } from "@/api/card-products";
import type { StaffCardProductCourseScopeInput } from "@/types/member-cards";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

const session = useSessionStore();
const checking = ref(true);
const loading = ref(true);
const saving = ref(false);
const courseId = ref(0);
const courseName = ref("");
// pick 模式（对标原版 subjectkey）：私教编辑页选卡，不落库，storage 回传
const pickMode = ref(false);
const pickToken = ref("");
const pickInitFees = ref<{ cardProductId: number; deductAmount?: string | number | null }[]>([]);

interface FeeDraft {
  id: number;
  name: string;
  cardType: string;
  saleStatus: string;
  faceGradient: string;
  enabled: boolean;
  fee: string; // 储值卡=金额 / 次卡=次数 / 期限卡=扣除天数（空或0=不扣）
  origEnabled: boolean;
  origFee: string;
}

const drafts = ref<FeeDraft[]>([]);

const canRead = computed(() => session.can("card-product.catalog.read"));
const canWrite = computed(() => session.can("card-product.editor.write"));
const enabledCount = computed(() => drafts.value.filter((draft) => draft.enabled).length);

const FACE_FALLBACK = "linear-gradient(135deg, #5f9ea8 0%, #3c7a86 100%)";

const CARD_TYPE_LABELS: Record<string, string> = {
  stored_value: "储值卡",
  count: "次卡",
  period: "期限卡",
};

function typeLabel(cardType: string) {
  return CARD_TYPE_LABELS[cardType] || cardType;
}

async function load() {
  if (!session.currentSiteId || !canRead.value || (!courseId.value && !pickMode.value)) {
    loading.value = false;
    return;
  }
  loading.value = true;
  try {
    const response = await fetchCardProducts(session.currentSiteId, 1, 50, undefined, "active");
    const items = response.data.items;

    // pick 模式：用传入 feeList 初始化（课目可能尚未落库），确定时仅回传不保存
    if (pickMode.value) {
      const feeByCardId = new Map(
        pickInitFees.value.map((fee) => [fee.cardProductId, fee.deductAmount != null && fee.deductAmount !== "" ? String(Number(fee.deductAmount)) : ""]),
      );
      drafts.value = items.map((item) => {
        const enabled = feeByCardId.has(item.id);
        const fee = feeByCardId.get(item.id) ?? "";
        return {
          id: item.id,
          name: item.name,
          cardType: item.cardType,
          saleStatus: item.saleStatus,
          faceGradient: item.faceGradient || FACE_FALLBACK,
          enabled,
          fee,
          origEnabled: enabled,
          origFee: fee,
        };
      });
      return;
    }

    const selectedIds = items
      .filter((item) => (item.courseScopeKeys ?? []).includes(courseId.value))
      .map((item) => item.id);
    // 已开启卡并行取本课当前扣费值
    const feeById = new Map<number, string>();
    await Promise.all(
      selectedIds.map(async (id) => {
        try {
          const detail = await fetchCardProduct(session.currentSiteId as number, id);
          const scope = detail.data.courseScopes.find(
            (item) => item.scopeKind === "single" && Number(item.scopeKey) === courseId.value,
          );
          feeById.set(id, scope?.priceOverride ?? "");
        } catch {
          feeById.set(id, "");
        }
      }),
    );
    drafts.value = items.map((item) => {
      const enabled = selectedIds.includes(item.id);
      const rawFee = feeById.get(item.id) ?? "";
      const fee = rawFee ? String(Number(rawFee)) : "";
      return {
        id: item.id,
        name: item.name,
        cardType: item.cardType,
        saleStatus: item.saleStatus,
        faceGradient: item.faceGradient || FACE_FALLBACK,
        enabled,
        fee,
        origEnabled: enabled,
        origFee: fee,
      };
    });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "会员卡加载失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}

// —— 期限卡「扣除期限」弹窗（对标原版 deductionDays） ——
const deductVisible = ref(false);
const deductRule = ref<"no" | "yes">("no");
const deductAmount = ref("");
const deductTarget = ref<FeeDraft | null>(null);

function openDeductPanel(draft: FeeDraft) {
  deductTarget.value = draft;
  const days = Number(draft.fee || 0);
  deductRule.value = days > 0 ? "yes" : "no";
  deductAmount.value = days > 0 ? String(days) : "";
  deductVisible.value = true;
}

function submitDeduct() {
  const draft = deductTarget.value;
  if (!draft) {
    deductVisible.value = false;
    return;
  }
  if (deductRule.value === "yes") {
    const days = Number.parseInt(deductAmount.value.trim() || "0", 10);
    if (!Number.isFinite(days) || days < 1) {
      uni.showToast({ title: "请填写扣除天数", icon: "none" });
      return;
    }
    draft.fee = String(days);
  } else {
    draft.fee = "";
  }
  deductVisible.value = false;
}

function deductLabel(draft: FeeDraft) {
  const days = Number(draft.fee || 0);
  return days > 0 ? `扣除${days}天` : "不扣";
}

function onSwitchChange(draft: FeeDraft, event: { detail: { value: boolean } }) {
  draft.enabled = event.detail.value;
}

// —— 保存（确 定 N张） ——
async function submit() {
  // pick 模式：仅回传选择结果（对标原版 subjectkey storage），由私教编辑页统一保存
  if (pickMode.value) {
    for (const draft of drafts.value) {
      if (!draft.enabled || draft.cardType === "period") continue;
      const trimmed = draft.fee.trim();
      if (trimmed && (!Number.isFinite(Number(trimmed)) || Number(trimmed) < 0)) {
        uni.showToast({ title: "扣费数值无效：" + draft.name, icon: "none" });
        return;
      }
    }
    const arr = drafts.value
      .filter((draft) => draft.enabled)
      .map((draft) => ({
        cardProductId: draft.id,
        cardName: draft.name,
        deductAmount: draft.fee.trim() ? Number(draft.fee.trim()) : null,
      }));
    uni.setStorageSync("private_fee_pick", { token: pickToken.value, arr, checknum: arr.length });
    uni.navigateBack();
    return;
  }

  if (!session.currentSiteId || !courseId.value) return;
  if (!canWrite.value) {
    uni.showToast({ title: "暂无会员卡设置权限", icon: "none" });
    return;
  }
  const changed = drafts.value.filter(
    (draft) => draft.enabled !== draft.origEnabled || (draft.enabled && draft.fee !== draft.origFee),
  );
  if (!changed.length) {
    uni.navigateBack();
    return;
  }
  // 金额/次数合法性
  for (const draft of changed) {
    if (!draft.enabled || draft.cardType === "period") continue;
    const trimmed = draft.fee.trim();
    if (trimmed && (!Number.isFinite(Number(trimmed)) || Number(trimmed) < 0)) {
      uni.showToast({ title: "扣费数值无效：" + draft.name, icon: "none" });
      return;
    }
  }

  saving.value = true;
  try {
    for (const draft of changed) {
      const detail = (await fetchCardProduct(session.currentSiteId, draft.id)).data;
      const scopes: StaffCardProductCourseScopeInput[] = detail.courseScopes
        .filter((item) => item.scopeKind === "single" && Number(item.scopeKey) !== courseId.value)
        .map((item, index) => ({
          scopeKind: "single" as const,
          scopeKey: item.scopeKey,
          displayName: item.displayName,
          priceOverride: item.priceOverride != null ? Number(item.priceOverride) : undefined,
          sortOrder: index,
        }));
      if (draft.enabled) {
        const trimmed = draft.fee.trim();
        scopes.push({
          scopeKind: "single" as const,
          scopeKey: String(courseId.value),
          displayName: courseName.value || undefined,
          priceOverride: trimmed ? Number(trimmed) : undefined,
          sortOrder: scopes.length,
        });
      }
      await updateCardProduct(session.currentSiteId, draft.id, {
        version: detail.version,
        cardType: detail.cardType as "stored_value" | "count" | "period",
        name: detail.name,
        price: Number(detail.price),
        description: detail.description,
        faceValue: detail.faceValue != null ? Number(detail.faceValue) : undefined,
        initialCount: detail.initialCount ?? undefined,
        validityDays: detail.validityDays ?? undefined,
        validityMode: detail.validityMode,
        activationMode: detail.activationMode,
        saleStatus: detail.saleStatus as "on_sale" | "stopped",
        sortOrder: detail.sortOrder,
        bookingRules: (detail.bookingRules as Record<string, unknown> | null) ?? undefined,
        scopeConfig: (detail.scopeConfig as Record<string, unknown> | null) ?? undefined,
        courseScopes: scopes,
      });
    }
    uni.showToast({ title: "已保存", icon: "success" });
    setTimeout(() => uni.navigateBack(), 600);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "保存失败", icon: "none" });
  } finally {
    saving.value = false;
  }
}

onLoad((query) => {
  courseId.value = Number(query?.courseId || 0);
  courseName.value = decodeURIComponent(String(query?.name || ""));
  pickMode.value = query?.pick === "1";
  pickToken.value = String(query?.token || "");
  if (query?.fees) {
    try {
      pickInitFees.value = JSON.parse(decodeURIComponent(String(query.fees)));
    } catch {
      pickInitFees.value = [];
    }
  }
});

onShow(async () => {
  checking.value = true;
  const authenticated = await requireStaffAuth();
  checking.value = false;
  if (!authenticated) return;
  await load();
});
</script>

<template>
  <u-loading-page :loading="checking || loading || saving" />
  <view v-if="!checking" class="page-shell">
    <view class="body-sheet">
      <u-empty v-if="!canRead" mode="permission" text="暂无会员卡查看权限" />

      <template v-else>
        <!-- 顶部提示（原版 top-tips） -->
        <view v-if="drafts.length" class="top-tips">
          <u-icon name="bell" size="18" color="#C96B30" />
          <text class="top-info">哪些卡可以预约这个课程，请打开开关并确认约课的费用</text>
        </view>

        <!-- 卡列表 -->
        <view v-for="draft in drafts" :key="draft.id" class="fee-item">
          <!-- 左：迷你卡面 -->
          <view class="mini-card" :class="{ gray: !draft.enabled }" :style="{ background: draft.faceGradient }">
            <text class="mini-name">{{ draft.name }}</text>
            <text class="mini-type">{{ typeLabel(draft.cardType) }}</text>
            <view v-if="draft.saleStatus === 'stopped'" class="stop-mask">
              <text class="stop-text">已停售</text>
            </view>
          </view>
          <!-- 右：扣费 + 开关 -->
          <view class="right-box">
            <view class="right-des">
              <template v-if="draft.enabled">
                <text class="fee-label">每节课扣费</text>
                <view v-if="draft.cardType === 'stored_value'" class="fee-input-row">
                  <input v-model="draft.fee" class="fee-input" type="digit" placeholder="输入金额" />
                  <text class="fee-unit">元</text>
                </view>
                <view v-else-if="draft.cardType === 'count'" class="fee-input-row">
                  <input v-model="draft.fee" class="fee-input" type="digit" placeholder="扣减次数" />
                  <text class="fee-unit">次</text>
                </view>
                <view v-else class="deduct-box" @tap="openDeductPanel(draft)">
                  <text class="deduct-text">{{ deductLabel(draft) }}</text>
                  <u-icon name="arrow-down" size="14" color="#7E7E7E" />
                </view>
              </template>
            </view>
            <switch
              class="fee-switch"
              :checked="draft.enabled"
              color="#FBD128"
              @change="onSwitchChange(draft, $event)"
            />
          </view>
        </view>

        <!-- 空态（原版：请先在【会员卡】中添加会员卡种类哦） -->
        <view v-if="!loading && !drafts.length" class="card-empty">
          <u-icon name="order" size="60" color="#dadada" />
          <text class="empty-text">请先在【会员卡】中添加会员卡种类哦</text>
        </view>

        <!-- 确 定(N张) -->
        <view v-if="drafts.length" class="btn-box">
          <button class="save-btn" :disabled="saving" @tap="submit">确 定({{ enabledCount }}张)</button>
        </view>

        <view class="brand-footer">觅境约课</view>
      </template>
    </view>

    <!-- 扣除期限弹窗（原版 deductionDays） -->
    <u-popup :show="deductVisible" mode="bottom" round="20" @close="deductVisible = false">
      <view class="panel">
        <text class="panel-title">扣除期限</text>
        <view class="deduct-tips">
          期限卡每过一天系统会自动减少一天，无需任何设置。这里的“不需要扣除”是指不需要额外扣除，比如会员持有一张团课卡，正常场景下她只能约团课；假如也允许她约精品课，那可以通过扣减天数的方式，会员约精品课会直接扣掉N天，用此方式加速耗卡
        </view>
        <view class="deduct-radio" @tap="deductRule = 'no'">
          <u-icon
            :name="deductRule === 'no' ? 'checkmark-circle-fill' : 'checkmark-circle'"
            :color="deductRule === 'no' ? '#ed920f' : '#bfbfbf'"
            size="20"
          />
          <text class="deduct-radio-text" :class="{ active: deductRule === 'no' }">不需要扣除</text>
        </view>
        <view class="deduct-radio" @tap="deductRule = 'yes'">
          <u-icon
            :name="deductRule === 'yes' ? 'checkmark-circle-fill' : 'checkmark-circle'"
            :color="deductRule === 'yes' ? '#ed920f' : '#bfbfbf'"
            size="20"
          />
          <text class="deduct-radio-text" :class="{ active: deductRule === 'yes' }">扣除</text>
          <input
            v-model="deductAmount"
            class="deduct-input"
            :disabled="deductRule === 'no'"
            type="number"
            placeholder="扣除天数"
          />
          <text class="deduct-radio-text">天</text>
        </view>
        <view v-if="deductRule === 'yes'" class="deduct-warning">
          <text class="warning-bold">警告：</text>“扣除”为高级功能，很少有人使用，若不理解上面的说明则您肯定用不到此功能，请改回【不需要扣除】
        </view>
        <button class="panel-confirm" @tap="submitDeduct">确　定</button>
      </view>
    </u-popup>
  </view>
</template>

<style scoped lang="scss">
.page-shell {
  min-height: 100vh;
  background: $color-brand-yellow;
}

.body-sheet {
  min-height: 100vh;
  padding: 30rpx 28rpx 60rpx;
  background: #fff;
  border-radius: 24rpx 24rpx 0 0;
  box-sizing: border-box;
}

// —— 顶部提示（原版 top-tips） ——
.top-tips {
  display: flex;
  align-items: flex-start;
  gap: 10rpx;
  padding: 20rpx 22rpx;
  background: #fdf6ec;
  border-radius: 14rpx;
}

.top-info {
  flex: 1;
  color: #c96b30;
  font-size: 24rpx;
  line-height: 36rpx;
}

// —— 卡行 ——
.fee-item {
  display: flex;
  align-items: center;
  gap: 22rpx;
  padding: 28rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
}

.mini-card {
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  flex-shrink: 0;
  width: 300rpx;
  height: 178rpx;
  padding: 20rpx 22rpx;
  border-radius: 16rpx;
  box-sizing: border-box;
  overflow: hidden;

  &.gray {
    filter: grayscale(100%);
    opacity: 0.75;
  }
}

.mini-name {
  overflow: hidden;
  color: #fff;
  font-size: 30rpx;
  font-weight: 600;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.mini-type {
  color: rgba(255, 255, 255, 0.8);
  font-size: 22rpx;
}

.stop-mask {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.45);
}

.stop-text {
  padding: 6rpx 20rpx;
  border: 1rpx solid rgba(255, 255, 255, 0.7);
  border-radius: 999rpx;
  color: #fff;
  font-size: 22rpx;
}

.right-box {
  display: flex;
  flex: 1;
  align-items: center;
  justify-content: space-between;
  min-width: 0;
}

.right-des {
  flex: 1;
  min-width: 0;
}

.fee-label {
  display: block;
  color: $color-text;
  font-size: 24rpx;
}

.fee-input-row {
  display: flex;
  align-items: center;
  gap: 8rpx;
  margin-top: 12rpx;
}

.fee-input {
  width: 150rpx;
  height: 60rpx;
  padding: 0 14rpx;
  background: $color-page;
  border-radius: 10rpx;
  color: $color-text;
  font-size: 26rpx;
}

.fee-unit {
  color: $color-text-secondary;
  font-size: 24rpx;
}

.deduct-box {
  display: inline-flex;
  align-items: center;
  gap: 6rpx;
  margin-top: 12rpx;
  padding: 12rpx 18rpx;
  background: $color-page;
  border-radius: 10rpx;
}

.deduct-text {
  color: $color-text;
  font-size: 26rpx;
}

.fee-switch {
  flex-shrink: 0;
  transform: scale(0.8);
}

// —— 空态 ——
.card-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 24rpx;
  padding: 120rpx 0 60rpx;
}

.empty-text {
  color: $color-text-tertiary;
  font-size: 26rpx;
}

// —— 确定按钮 ——
.btn-box {
  display: flex;
  justify-content: center;
  margin-top: 60rpx;
}

.save-btn {
  width: 458rpx;
  height: 83rpx;
  line-height: 83rpx;
  background: $color-brand-yellow;
  border-radius: 42rpx;
  color: $color-text;
  font-size: 32rpx;
  font-weight: 500;

  &[disabled] {
    opacity: 0.6;
    color: $color-text;
    background: $color-brand-yellow;
  }
}

.save-btn::after {
  border: 0;
}

.brand-footer {
  margin: 90rpx 0 20rpx;
  color: #d8d8d8;
  font-size: 26rpx;
  letter-spacing: 6rpx;
  text-align: center;
}

// —— 扣除期限弹窗 ——
.panel {
  display: flex;
  flex-direction: column;
  gap: 24rpx;
  padding: 40rpx 32rpx calc(40rpx + env(safe-area-inset-bottom));
}

.panel-title {
  font-size: 32rpx;
  font-weight: 600;
  text-align: center;
  color: $color-text;
}

.deduct-tips {
  padding: 20rpx 22rpx;
  background: #fdf6ec;
  border-radius: 14rpx;
  color: #c96b30;
  font-size: 22rpx;
  line-height: 34rpx;
}

.deduct-radio {
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.deduct-radio-text {
  color: $color-text-secondary;
  font-size: 28rpx;

  &.active {
    color: $color-text;
    font-weight: 500;
  }
}

.deduct-input {
  width: 160rpx;
  height: 64rpx;
  padding: 0 16rpx;
  background: $color-page;
  border-radius: 10rpx;
  color: $color-text;
  font-size: 26rpx;
  text-align: center;
}

.deduct-warning {
  color: $color-danger;
  font-size: 24rpx;
  line-height: 36rpx;
}

.warning-bold {
  font-weight: 600;
}

.panel-confirm {
  height: 83rpx;
  margin-top: 6rpx;
  line-height: 83rpx;
  background: $color-brand-yellow;
  border-radius: 42rpx;
  color: $color-text;
  font-size: 32rpx;
  font-weight: 500;
}

.panel-confirm::after {
  border: 0;
}
</style>
