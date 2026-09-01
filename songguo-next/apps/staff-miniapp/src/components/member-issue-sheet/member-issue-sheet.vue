<script setup lang="ts">
/** 对标原版 immediatelyCard/new_card + card_limit：选卡弹层 → 确认额度弹层 → 发卡 */
import { computed, ref, watch } from "vue";
import { fetchAllCardProducts } from "@/api/card-products";
import { issueMemberCard } from "@/api/member-cards";
import { fetchAllCompensationRoles, fetchStaffCompensationRoleAssignmentSets } from "@/api/compensation";
import { fetchStaffDirectory } from "@/api/staff-directory";
import { fetchMemberWallet } from "@/api/wallet";
import FfBottomSheet from "@/components/ff-bottom-sheet/ff-bottom-sheet.vue";
import ShareAssignmentEditor from "@/components/share-assignment-editor/share-assignment-editor.vue";
import { useSessionStore } from "@/stores/session";
import type {
  StaffCardProductCatalogItem,
  StaffMemberCardIssueInput,
  StaffMemberCardIssueShareAssignment,
} from "@/types/member-cards";
import type { CompensationRole } from "@/types/compensation";
import type { StaffDirectoryListItem } from "@/types/staff-directory";
import type { MemberWallet } from "@/types/wallet";
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
const paymentMethod = ref<"online" | "balance">("online");
const shareRoles = ref<CompensationRole[]>([]);
const staffOptions = ref<StaffDirectoryListItem[]>([]);
const staffRoleIds = ref<Record<number, number[]>>({});
const shareAssignments = ref<StaffMemberCardIssueShareAssignment[]>([]);
const wallet = ref<MemberWallet | null>(null);
/** immediate | first_use | first_class | keep_pending | specified */
type OpeningType = NonNullable<StaffMemberCardIssueInput["openingType"]>;
const openingType = ref<OpeningType>("immediate");
const openingTypeOptions: Array<{ value: OpeningType; label: string }> = [
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
const availablePaymentMethods = computed<Array<"online" | "balance">>(() =>
  selected.value?.allowedPaymentMethods == null
    ? ["online", "balance"]
    : selected.value.allowedPaymentMethods.filter((method) => method === "online" || method === "balance"),
);
const canReadCompensationRoles = computed(() => session.can("compensation.role.read"));
const canReadStaffDirectory = computed(() => session.can("staff.directory.read"));
const canReadWallet = computed(() => session.can("wallet.read"));
const canConfigureShareAssignments = computed(
  () => canReadCompensationRoles.value && canReadStaffDirectory.value,
);
const balancePaymentLabel = computed(() => wallet.value
  ? `会员余额（可用 ¥${wallet.value.balance}）`
  : "会员余额（由服务端校验）");

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
  products.value = [];
  shareRoles.value = [];
  staffOptions.value = [];
  staffRoleIds.value = {};
  shareAssignments.value = [];
  wallet.value = null;
  try {
    const [response, roleResponse, staffResponse, memberWallet] = await Promise.all([
      fetchAllCardProducts(session.currentSiteId),
      canConfigureShareAssignments.value
        ? fetchAllCompensationRoles(session.currentSiteId)
        : Promise.resolve([]),
      canConfigureShareAssignments.value
        ? fetchStaffDirectory(session.currentSiteId)
        : Promise.resolve({ items: [], activeCount: 0, departedCount: 0 }),
      props.memberId && canReadWallet.value
        ? fetchMemberWallet(session.currentSiteId, props.memberId)
        : Promise.resolve(null),
    ]);
    const items = [...response].sort((a, b) => {
      const aOn = a.saleStatus === "on_sale" || a.saleStatus === "1" ? 1 : 0;
      const bOn = b.saleStatus === "on_sale" || b.saleStatus === "1" ? 1 : 0;
      return bOn - aOn;
    });
    products.value = items;
    shareRoles.value = roleResponse.filter((role) => role.type === "share" && role.status === "active");
    staffOptions.value = staffResponse.items.filter((staff) => staff.status === "active");
    const assignmentSets = shareRoles.value.length
      ? await fetchStaffCompensationRoleAssignmentSets(
          session.currentSiteId,
          staffOptions.value.map((staff) => staff.id),
        )
      : new Map();
    if (shareRoles.value.length && staffOptions.value.some((staff) => !assignmentSets.get(staff.id))) {
      throw new Error("员工业务角色加载失败");
    }
    staffRoleIds.value = Object.fromEntries(
      staffOptions.value.map((staff) => [staff.id, assignmentSets.get(staff.id)?.roleIds ?? []]),
    );
    wallet.value = memberWallet;
  } catch (error) {
    products.value = [];
    uni.showToast({ title: error instanceof Error ? error.message : "发卡资料加载失败", icon: "none" });
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
  const firstPaymentMethod = availablePaymentMethods.value[0];
  if (!firstPaymentMethod) {
    selected.value = null;
    uni.showToast({ title: "该卡未配置可用支付方式", icon: "none" });
    return;
  }
  paymentMethod.value = firstPaymentMethod;
  shareAssignments.value = [];
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

function pickPaymentMethod() {
  if (availablePaymentMethods.value.length < 2) return;
  uni.showActionSheet({
    itemList: availablePaymentMethods.value.map((method) => method === "balance"
      ? balancePaymentLabel.value
      : "线上已收款（人工确认）"),
    success: ({ tapIndex }) => { paymentMethod.value = availablePaymentMethods.value[tapIndex]; },
  });
}

function confirmPaymentRecording(amount: number) {
  if (paymentMethod.value === "online") {
    return new Promise<boolean>((resolve) => {
      uni.showModal({
        title: "确认线上款项已收取",
        content: `请核实已通过线上渠道收到 ¥${amount.toFixed(2)}。本操作只记录人工收款，不会拉起微信支付。`,
        confirmText: "确认已收款",
        success: (result) => resolve(!!result.confirm),
        fail: () => resolve(false),
      });
    });
  }

  return new Promise<boolean>((resolve) => {
    uni.showModal({
      title: "确认余额支付",
      content: `将由服务端原子校验并扣除会员余额 ¥${amount.toFixed(2)}。`,
      success: (result) => resolve(!!result.confirm),
      fail: () => resolve(false),
    });
  });
}

function validateShareAssignments() {
  const seen = new Set<string>();
  const totals = new Map<number, number>();
  for (const assignment of shareAssignments.value) {
    const role = shareRoles.value.find((item) => item.id === assignment.roleId);
    if (!role || !staffRoleIds.value[assignment.staffId]?.includes(assignment.roleId)) {
      return "B 类型分成归属与员工业务角色不匹配，请重新选择";
    }
    const key = `${assignment.roleId}:${assignment.staffId}`;
    if (seen.has(key)) return `${role.name} 不能重复选择同一员工`;
    seen.add(key);
    if (!Number.isInteger(assignment.allocationBps) || assignment.allocationBps <= 0 || assignment.allocationBps > 10000) {
      return `${role.name} 的分配比例需大于 0% 且不超过 100%`;
    }
    totals.set(assignment.roleId, (totals.get(assignment.roleId) ?? 0) + assignment.allocationBps);
  }
  for (const [roleId, total] of totals) {
    if (total !== 10000) {
      return `${shareRoles.value.find((item) => item.id === roleId)?.name || "B 角色"} 的分配比例合计必须为 100%`;
    }
  }
  return "";
}

async function submitIssue() {
  if (!props.memberId || !session.currentSiteId || !selected.value) return;
  const amountInput = price.value.trim();
  const actualAmount = Number(amountInput);
  if (purchaseMode.value === 1
    && (!/^\d{1,10}(?:\.\d{1,2})?$/.test(amountInput) || !Number.isFinite(actualAmount))) {
    uni.showToast({ title: "请输入有效实付金额", icon: "none" });
    return;
  }
  const issueReason = purchaseMode.value === 0 ? "员工端录旧卡" : "员工端新购卡";
  if (purchaseMode.value === 1 && issueReason.trim().length < 4) {
    uni.showToast({ title: "收款说明至少 4 个字", icon: "none" });
    return;
  }
  const payload: StaffMemberCardIssueInput = {
    cardProductId: selected.value.id,
    commandKey: createCommandKey(),
    reason: issueReason,
  };
  if (purchaseMode.value === 1) {
    payload.actualAmount = actualAmount.toFixed(2);
    payload.paymentMethod = paymentMethod.value;
    payload.openingType = openingType.value;
    const shareValidation = validateShareAssignments();
    if (shareValidation) {
      uni.showToast({ title: shareValidation, icon: "none" });
      return;
    }
    const selectedShares = shareAssignments.value.map((assignment) => ({ ...assignment }));
    if (selectedShares.length) payload.shareAssignments = selectedShares;
  }
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
  if (purchaseMode.value === 1 && !(await confirmPaymentRecording(actualAmount))) return;

  submitting.value = true;
  try {
    await issueMemberCard(session.currentSiteId, props.memberId, payload);
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
      <view v-if="purchaseMode === 1" class="field-row" @tap="pickPaymentMethod">
        <text class="balances">支付方式</text>
        <view class="input-wrap link">
          <text>{{ paymentMethod === "balance" ? "会员余额" : "线上已收款（人工确认）" }}</text>
          <u-icon name="arrow-right" size="14" color="#989898" />
        </view>
      </view>
      <view v-if="purchaseMode === 1 && paymentMethod === 'balance' && wallet" class="wallet-hint">会员钱包余额 ¥{{ wallet.balance }}，最终扣款由服务端原子校验</view>
      <view v-else-if="purchaseMode === 1 && paymentMethod === 'balance'" class="wallet-hint">当前账号无钱包查看权限，余额与防超扣由服务端校验</view>
      <view v-else-if="purchaseMode === 1" class="wallet-hint">请确认款项已在线收取；确定后会以当前登录员工身份记录真实实付，不会拉起微信支付。</view>
      <template v-if="purchaseMode === 1 && canConfigureShareAssignments && shareRoles.length">
        <view class="field-section-title">B 类型分成归属（可选）</view>
        <ShareAssignmentEditor
          v-model="shareAssignments"
          :roles="shareRoles"
          :staff="staffOptions"
          :staff-role-ids="staffRoleIds"
        />
      </template>
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

.wallet-hint,
.field-section-title {
  margin: 12rpx 36rpx 0;
  color: #989898;
  font-size: 22rpx;
}

.field-section-title {
  margin-top: 26rpx;
  color: #181818;
  font-size: 25rpx;
  font-weight: 600;
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
