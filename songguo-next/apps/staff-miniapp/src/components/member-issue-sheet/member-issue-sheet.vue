<script setup lang="ts">
/** 对标原版 immediatelyCard/new_card + card_limit：选卡弹层 → 确认额度弹层 → 发卡 */
import { computed, ref, watch } from "vue";
import { fetchCardProducts } from "@/api/card-products";
import { issueMemberCard, updateMemberCardOpeningType } from "@/api/member-cards";
import FfBottomSheet from "@/components/ff-bottom-sheet/ff-bottom-sheet.vue";
import { useSessionStore } from "@/stores/session";
import type { StaffCardProductCatalogItem } from "@/types/member-cards";
import { createCommandKey } from "@/utils/command-key";

const props = defineProps<{
  show: boolean;
  memberId: number | null;
}>();

const emit = defineEmits<{
  (e: "update:show", value: boolean): void;
  (e: "success"): void;
}>();

const session = useSessionStore();
const keyword = ref("");
const products = ref<StaffCardProductCatalogItem[]>([]);
const loading = ref(false);
const submitting = ref(false);
const selected = ref<StaffCardProductCatalogItem | null>(null);
const showConfirm = ref(false);
/** 1=新购卡 0=录旧卡 */
const purchaseMode = ref<1 | 0>(1);
const openingBalance = ref("");
const openingCount = ref("");
const price = ref("");
/** immediate | first_use | first_class | keep_pending | specified */
const openingType = ref("immediate");
const openingTypeOptions = [
  { value: "immediate", label: "购卡后立即开卡" },
  { value: "first_use", label: "首次使用时自动开卡" },
  { value: "first_class", label: "首次上课时自动开卡" },
  { value: "keep_pending", label: "保持未开卡状态" },
];
const openingTypeLabel = computed(
  () => openingTypeOptions.find((o) => o.value === openingType.value)?.label || "请选择开卡时间",
);

const filtered = computed(() => {
  const q = keyword.value.trim();
  if (!q) return products.value;
  return products.value.filter((p) => p.name.includes(q));
});

const isStoredValue = computed(() => selected.value?.cardType === "stored_value");
const isCount = computed(() => selected.value?.cardType === "count");

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      keyword.value = "";
      selected.value = null;
      showConfirm.value = false;
      void loadProducts();
    }
  },
);

async function loadProducts() {
  if (!session.currentSiteId) return;
  loading.value = true;
  try {
    const response = await fetchCardProducts(session.currentSiteId);
    const items = [...response.data.items].sort((a, b) => {
      const aOn = a.saleStatus === "on_sale" || a.saleStatus === "1" ? 1 : 0;
      const bOn = b.saleStatus === "on_sale" || b.saleStatus === "1" ? 1 : 0;
      return bOn - aOn;
    });
    products.value = items;
  } catch {
    products.value = [];
    uni.showToast({ title: "卡产品加载失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}

function isStopped(product: StaffCardProductCatalogItem) {
  return product.saleStatus === "stopped" || product.saleStatus === "0" || product.saleStatus === "off_sale";
}

function cardFaceStyle(product: StaffCardProductCatalogItem) {
  if (product.faceGradient) return { background: product.faceGradient };
  return { background: "linear-gradient(135deg, #fbd128 0%, #f0a020 100%)" };
}

function cardTypeLabel(cardType: string) {
  return ({ stored_value: "储值卡", count: "次卡", period: "期限卡" } as Record<string, string>)[cardType] || cardType;
}

function cardMeta(product: StaffCardProductCatalogItem) {
  if (product.cardType === "stored_value") return product.faceValue ? `面值 ¥${product.faceValue}` : `售价 ¥${product.price}`;
  if (product.cardType === "count") return product.initialCount != null ? `${product.initialCount} 次` : `售价 ¥${product.price}`;
  return product.validityDays != null ? `${product.validityDays} 天` : `售价 ¥${product.price}`;
}

function onPick(product: StaffCardProductCatalogItem) {
  if (isStopped(product)) {
    uni.showToast({ title: "该卡已停售", icon: "none" });
    return;
  }
  selected.value = product;
  purchaseMode.value = 1;
  openingBalance.value = product.faceValue || "";
  openingCount.value = product.initialCount != null ? String(product.initialCount) : "";
  price.value = product.price || "";
  openingType.value = "immediate";
  showConfirm.value = true;
}

function closeSelect() {
  emit("update:show", false);
}

function closeConfirm() {
  showConfirm.value = false;
}

function pickOpeningType() {
  uni.showActionSheet({
    itemList: openingTypeOptions.map((o) => o.label),
    success: ({ tapIndex }) => {
      openingType.value = openingTypeOptions[tapIndex].value;
    },
  });
}

async function submitIssue() {
  if (!props.memberId || !session.currentSiteId || !selected.value) return;
  submitting.value = true;
  try {
    const reasonParts = [
      purchaseMode.value === 0 ? "录旧卡" : "新购卡",
      price.value.trim() ? `实收${price.value}` : "",
    ].filter(Boolean);
    const payload: {
      cardProductId: number;
      commandKey: string;
      openingBalance?: number;
      openingCount?: number;
      reason?: string;
    } = {
      cardProductId: selected.value.id,
      commandKey: createCommandKey(),
      reason: reasonParts.join(" · "),
    };
    if (isStoredValue.value && openingBalance.value.trim()) {
      const amount = Number(openingBalance.value);
      if (!Number.isFinite(amount) || amount < 0) {
        uni.showToast({ title: "请输入有效额度", icon: "none" });
        return;
      }
      payload.openingBalance = amount;
    }
    if (isCount.value && openingCount.value.trim()) {
      const count = Number.parseInt(openingCount.value, 10);
      if (!Number.isFinite(count) || count < 1) {
        uni.showToast({ title: "请输入有效次数", icon: "none" });
        return;
      }
      payload.openingCount = count;
    }
    const issued = await issueMemberCard(session.currentSiteId, props.memberId, payload);
    if (purchaseMode.value === 1 && openingType.value) {
      try {
        await updateMemberCardOpeningType(session.currentSiteId, issued.data.id, openingType.value);
      } catch {
        // 开卡方式写入失败不阻断发卡成功
      }
    }
    uni.showToast({ title: "添加成功", icon: "none" });
    showConfirm.value = false;
    emit("update:show", false);
    emit("success");
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "发卡失败", icon: "none" });
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <!-- 选择会员卡 -->
  <FfBottomSheet
    :show="show && !showConfirm"
    title="选择会员卡"
    :height-rpx="1100"
    :show-confirm="false"
    flush-body
    @update:show="closeSelect"
  >
    <view class="main-box">
      <view class="search-warp">
        <u-search
          v-model="keyword"
          placeholder="会员卡名称"
          :show-action="false"
          height="69"
          search-icon-color="#FBD128"
          bg-color="#f5f5f5"
        />
      </view>
      <view class="form-box">
        <u-loading-icon v-if="loading" mode="circle" color="#FBD128" />
        <view v-else-if="filtered.length === 0" class="empty">暂无可用卡产品</view>
        <view v-else class="cardList">
          <view
            v-for="product in filtered"
            :key="product.id"
            class="card-row"
            @tap="onPick(product)"
          >
            <view class="card_wrap" :class="{ gray: isStopped(product) }">
              <view v-if="isStopped(product)" class="mask" />
              <view v-if="isStopped(product)" class="stopped-badge">已停售</view>
              <view class="member-card-face" :style="cardFaceStyle(product)">
                <view class="card-face-top">
                  <text class="card-face-name">{{ product.name }}</text>
                  <text class="card-face-type">{{ cardTypeLabel(product.cardType) }}</text>
                </view>
                <view class="card-face-bottom">
                  <text>{{ cardMeta(product) }}</text>
                  <text>¥{{ product.price }}</text>
                </view>
              </view>
            </view>
          </view>
        </view>
      </view>
    </view>
  </FfBottomSheet>

  <!-- 确认额度 -->
  <FfBottomSheet
    :show="showConfirm"
    title="确认额度"
    :height-rpx="1100"
    confirm-text="确　定"
    :confirm-disabled="submitting"
    flush-body
    @update:show="closeConfirm"
    @confirm="submitIssue"
  >
    <view v-if="selected" class="confirm-box">
      <view class="card_wrap">
        <view class="member-card-face" :style="cardFaceStyle(selected)">
          <view class="card-face-top">
            <text class="card-face-name">{{ selected.name }}</text>
            <text class="card-face-type">{{ cardTypeLabel(selected.cardType) }}</text>
          </view>
          <view class="card-face-bottom">
            <text>{{ cardMeta(selected) }}</text>
            <text>¥{{ selected.price }}</text>
          </view>
        </view>
      </view>

      <view class="radio-row">
        <u-radio-group v-model="purchaseMode" placement="row">
          <u-radio :name="1" label="新购卡" labelSize="28rpx" activeColor="#FBD128" />
          <u-radio :name="0" label="录旧卡" labelSize="28rpx" activeColor="#FBD128" />
        </u-radio-group>
        <view v-if="purchaseMode === 0" class="radio_title">通常刚用本软件而录入老会员时使用</view>
      </view>

      <view v-if="isStoredValue" class="field-row">
        <text class="balances">卡内额度</text>
        <view class="input-wrap">
          <input v-model="openingBalance" class="com-input" type="digit" placeholder="含多少" />
          <text>元</text>
        </view>
      </view>
      <view v-else-if="isCount" class="field-row">
        <text class="balances">卡内额度</text>
        <view class="input-wrap">
          <input v-model="openingCount" class="com-input" type="digit" placeholder="含多少" />
          <text>次</text>
        </view>
      </view>

      <view v-if="purchaseMode === 1" class="field-row" @tap="pickOpeningType">
        <text class="balances">开卡时间</text>
        <view class="input-wrap link">
          <text>{{ openingTypeLabel }}</text>
          <u-icon name="arrow-right" size="14" color="#989898" />
        </view>
      </view>

      <view v-if="purchaseMode === 1" class="field-row">
        <text class="balances">实际收款</text>
        <view class="input-wrap">
          <input v-model="price" class="com-input" type="digit" placeholder="实际出售价格" />
          <text>元</text>
        </view>
      </view>
      <view class="hint">有效期默认沿用卡产品设置；发卡后可在卡详情延长</view>
    </view>
  </FfBottomSheet>
</template>

<style scoped lang="scss">
.main-box {
  position: relative;
  min-height: 800rpx;
}

.search-warp {
  position: sticky;
  top: 0;
  z-index: 10;
  padding: 18rpx 35rpx 15rpx;
  background: #fff;
}

.form-box {
  padding: 24rpx 65rpx 24rpx;
}

.empty {
  padding: 80rpx 0;
  color: #989898;
  font-size: 26rpx;
  text-align: center;
}

.card-row {
  margin-bottom: 28rpx;
}

.card_wrap {
  position: relative;
  width: 620rpx;
  margin: 0 auto;
}

.card_wrap.gray {
  filter: grayscale(100%);
}

.mask {
  position: absolute;
  inset: 0;
  z-index: 2;
}

.stopped-badge {
  position: absolute;
  right: 20rpx;
  bottom: 12rpx;
  z-index: 3;
  padding: 6rpx 16rpx;
  border-radius: 8rpx;
  background: rgba(0, 0, 0, 0.45);
  color: #fff;
  font-size: 22rpx;
}

.member-card-face {
  box-sizing: border-box;
  width: 100%;
  height: 280rpx;
  padding: 28rpx 32rpx;
  border-radius: 18rpx;
  color: #fff;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.12);
}

.card-face-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.card-face-name {
  max-width: 70%;
  font-size: 34rpx;
  font-weight: 600;
}

.card-face-type {
  padding: 4rpx 14rpx;
  border-radius: 20rpx;
  background: rgba(0, 0, 0, 0.2);
  font-size: 20rpx;
}

.card-face-bottom {
  position: absolute;
  right: 32rpx;
  bottom: 28rpx;
  left: 32rpx;
  display: flex;
  justify-content: space-between;
  font-size: 24rpx;
}

.card_wrap .member-card-face {
  position: relative;
}

.confirm-box {
  padding: 24rpx 65rpx 40rpx;
}

.radio-row {
  margin: 36rpx 0 20rpx;
}

.radio_title {
  margin-top: 12rpx;
  color: #989898;
  font-size: 22rpx;
}

.field-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 28rpx;
  padding-bottom: 20rpx;
  border-bottom: 1rpx solid #f0f0f0;
}

.balances {
  color: #181818;
  font-size: 28rpx;
}

.input-wrap {
  display: flex;
  align-items: center;
  gap: 8rpx;
  color: #989898;
  font-size: 26rpx;
}

.com-input {
  width: 200rpx;
  height: 64rpx;
  text-align: center;
  background: #f5f5f5;
  border-radius: 12rpx;
  color: #181818;
  font-size: 28rpx;
}

.input-wrap.link {
  gap: 4rpx;
  color: #181818;
}

.hint {
  margin-top: 28rpx;
  color: #dadada;
  font-size: 22rpx;
  line-height: 1.5;
}
</style>
