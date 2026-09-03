<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { fetchAllCardProducts } from "@/api/card-products";
import { issueMemberCard } from "@/api/member-cards";
import { fetchAllCompensationRoles, fetchStaffCompensationRoleAssignmentSets } from "@/api/compensation";
import { fetchStaffDirectory } from "@/api/staff-directory";
import { fetchMemberWallet } from "@/api/wallet";
import ShareAssignmentEditor from "@/components/share-assignment-editor/share-assignment-editor.vue";
import { requireStaffAuth } from "@/auth/guard";
import { useApiClient } from "@/api/client";
import { useSessionStore } from "@/stores/session";
import type { CrmMember } from "@/types/crm";
import type {
  StaffCardProductCatalogItem,
  StaffMemberCardIssueInput,
  StaffMemberCardIssueShareAssignment,
} from "@/types/member-cards";
import type { CompensationRole } from "@/types/compensation";
import type { StaffDirectoryListItem } from "@/types/staff-directory";
import type { MemberWallet } from "@/types/wallet";
import { createCommandKey } from "@/utils/command-key";

const session = useSessionStore();
const memberId = ref<number>();
const member = ref<CrmMember | null>(null);
const products = ref<StaffCardProductCatalogItem[]>([]);
const selectedProductId = ref<number>();
const openingBalance = ref("");
const openingCount = ref("");
const reason = ref("员工端新购卡");
const actualAmount = ref("");
const paymentMethod = ref<"online" | "balance">("online");
const shareRoles = ref<CompensationRole[]>([]);
const staffOptions = ref<StaffDirectoryListItem[]>([]);
const staffRoleIds = ref<Record<number, number[]>>({});
const shareAssignments = ref<StaffMemberCardIssueShareAssignment[]>([]);
const wallet = ref<MemberWallet | null>(null);
const loading = ref(true);
const submitting = ref(false);
const errorMessage = ref("");

const canIssue = computed(() => session.can("member-card.issue"));
const canLoadProducts = computed(() => session.can("card-product.catalog.read"));
const canReadCompensationRoles = computed(() => session.can("compensation.role.read"));
const canReadStaffDirectory = computed(() => session.can("staff.directory.read"));
const canReadWallet = computed(() => session.can("wallet.read"));
const canConfigureShareAssignments = computed(
  () => canReadCompensationRoles.value && canReadStaffDirectory.value,
);

const selectedProduct = computed(() =>
  products.value.find((item) => item.id === selectedProductId.value) ?? null,
);

const isStoredValue = computed(() => selectedProduct.value?.cardType === "stored_value");
const isCount = computed(() => selectedProduct.value?.cardType === "count");
const availablePaymentMethods = computed<Array<"online" | "balance">>(() =>
  selectedProduct.value?.allowedPaymentMethods == null
    ? ["online", "balance"]
    : selectedProduct.value.allowedPaymentMethods.filter((method) => method === "online" || method === "balance"),
);
const balancePaymentLabel = computed(() => wallet.value
  ? `会员余额（可用 ¥${wallet.value.balance}）`
  : "会员余额（由服务端校验）");

function cardTypeLabel(cardType: string) {
  return ({ stored_value: "储值卡", count: "次卡", period: "期限卡" } as Record<string, string>)[cardType] || cardType;
}

function productSummary(product: StaffCardProductCatalogItem) {
  const parts = [cardTypeLabel(product.cardType), `¥${product.price}`];
  if (product.faceValue) parts.push(`面值 ¥${product.faceValue}`);
  if (product.initialCount != null) parts.push(`${product.initialCount} 次`);
  if (product.validityDays != null) parts.push(`${product.validityDays} 天`);
  return parts.join(" · ");
}

function productHint(product: StaffCardProductCatalogItem | null) {
  if (!product) return "";
  if (product.cardType === "stored_value") {
    return product.faceValue ? `默认开卡余额 ¥${product.faceValue}，可填写自定义开卡金额` : "可填写开卡金额";
  }
  if (product.cardType === "count") {
    return product.initialCount != null
      ? `默认开卡次数 ${product.initialCount}，可填写自定义开卡次数`
      : "可填写开卡次数";
  }
  if (product.cardType === "period") {
    return product.validityDays != null ? `开卡后有效期 ${product.validityDays} 天` : "";
  }
  return "";
}

async function loadMember() {
  if (!memberId.value || !session.currentSiteId) return;
  const response = await useApiClient().request<CrmMember>(
    `/staff/sites/${session.currentSiteId}/members/${memberId.value}`,
  );
  member.value = response.data;
}

async function loadProducts() {
  if (!session.currentSiteId || !canLoadProducts.value) return;
  const response = await fetchAllCardProducts(session.currentSiteId);
  products.value = response.filter((item) => item.saleStatus === "on_sale");
}

async function loadSaleOptions() {
  if (!session.currentSiteId || !memberId.value) return;
  const [roleResponse, staffResponse, memberWallet] = await Promise.all([
    canConfigureShareAssignments.value
      ? fetchAllCompensationRoles(session.currentSiteId)
      : Promise.resolve([]),
    canConfigureShareAssignments.value
      ? fetchStaffDirectory(session.currentSiteId)
      : Promise.resolve({ items: [], activeCount: 0, departedCount: 0 }),
    canReadWallet.value ? fetchMemberWallet(session.currentSiteId, memberId.value) : Promise.resolve(null),
  ]);
  shareRoles.value = roleResponse.filter((role) => role.type === "share" && role.status === "active");
  staffOptions.value = staffResponse.items.filter((staff) => staff.status === "active");
  const assignmentSets = shareRoles.value.length
    ? await fetchStaffCompensationRoleAssignmentSets(
        session.currentSiteId,
        staffOptions.value.map((staff) => staff.id),
      )
    : new Map();
  if (shareRoles.value.length && staffOptions.value.some((staff) => !assignmentSets.get(staff.id))) {
    throw new Error("员工业务角色加载失败，请重试");
  }
  staffRoleIds.value = Object.fromEntries(
    staffOptions.value.map((staff) => [staff.id, assignmentSets.get(staff.id)?.roleIds ?? []]),
  );
  wallet.value = memberWallet;
}

async function loadPage() {
  if (!memberId.value || memberId.value < 1 || !session.currentSiteId) {
    errorMessage.value = "会员参数或场馆上下文无效";
    loading.value = false;
    return;
  }
  if (!canIssue.value) {
    errorMessage.value = "暂无发卡权限";
    loading.value = false;
    return;
  }
  if (!canLoadProducts.value) {
    errorMessage.value = "暂无卡产品目录查看权限";
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    await Promise.all([loadMember(), loadProducts(), loadSaleOptions()]);
    if (products.value.length === 0) {
      errorMessage.value = "暂无可售卡产品";
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "页面加载失败";
  } finally {
    loading.value = false;
  }
}

function chooseProduct() {
  if (products.value.length === 0) return;
  uni.showActionSheet({
    itemList: products.value.map((item) => `${item.name}（${cardTypeLabel(item.cardType)}）`),
    success: ({ tapIndex }) => {
      const product = products.value[tapIndex];
      selectedProductId.value = product.id;
      openingBalance.value = "";
      openingCount.value = "";
      actualAmount.value = product.price || "";
      const firstPaymentMethod = availablePaymentMethods.value[0];
      if (!firstPaymentMethod) {
        selectedProductId.value = undefined;
        uni.showToast({ title: "该卡未配置可用支付方式", icon: "none" });
        return;
      }
      paymentMethod.value = firstPaymentMethod;
      shareAssignments.value = [];
    },
  });
}

function choosePaymentMethod() {
  if (availablePaymentMethods.value.length < 2) return;
  const labels = availablePaymentMethods.value.map((method) => method === "balance"
    ? balancePaymentLabel.value
    : "线上已收款（人工确认）");
  uni.showActionSheet({
    itemList: labels,
    success: ({ tapIndex }) => { paymentMethod.value = availablePaymentMethods.value[tapIndex]; },
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

async function submit() {
  if (!memberId.value || !session.currentSiteId || !selectedProduct.value) {
    uni.showToast({ title: "请选择卡产品", icon: "none" });
    return;
  }
  const amountInput = actualAmount.value.trim();
  const paid = Number(amountInput);
  if (!/^\d{1,10}(?:\.\d{1,2})?$/.test(amountInput) || !Number.isFinite(paid)) {
    uni.showToast({ title: "请输入有效实付金额", icon: "none" });
    return;
  }
  const trimmedReason = reason.value.trim();
  if (trimmedReason.length < 4) {
    uni.showToast({ title: "收款说明必填，且至少 4 个字", icon: "none" });
    return;
  }
  const shareValidation = validateShareAssignments();
  if (shareValidation) {
    uni.showToast({ title: shareValidation, icon: "none" });
    return;
  }
  const selectedShares = shareAssignments.value.map((assignment) => ({ ...assignment }));
  const payload: StaffMemberCardIssueInput = {
    cardProductId: selectedProduct.value.id,
    commandKey: createCommandKey(),
    actualAmount: paid.toFixed(2),
    paymentMethod: paymentMethod.value,
    reason: trimmedReason,
  };
  if (selectedShares.length) payload.shareAssignments = selectedShares;
  if (isStoredValue.value && openingBalance.value.trim()) {
    const amount = Number(openingBalance.value);
    if (!Number.isFinite(amount) || amount < 0) {
      uni.showToast({ title: "请输入有效开卡金额", icon: "none" });
      return;
    }
    payload.openingBalance = amount;
  }
  if (isCount.value && openingCount.value.trim()) {
    const count = Number.parseInt(openingCount.value, 10);
    if (!Number.isFinite(count) || count < 1) {
      uni.showToast({ title: "请输入有效开卡次数", icon: "none" });
      return;
    }
    payload.openingCount = count;
  }

  const confirmed = await new Promise<boolean>((resolve) => {
    uni.showModal({
      title: "确认发卡",
      content: paymentMethod.value === "online"
        ? `为「${member.value?.name || "会员"}」发放「${selectedProduct.value!.name}」？\n已核实线上收款 ¥${paid.toFixed(2)}。本操作只记录人工收款，不会拉起微信支付。\n收款说明：${trimmedReason}`
        : `为「${member.value?.name || "会员"}」发放「${selectedProduct.value!.name}」？\n将由服务端原子校验并扣除会员余额 ¥${paid.toFixed(2)}。\n收款说明：${trimmedReason}`,
      confirmText: paymentMethod.value === "online" ? "确认已收款" : "确认发卡",
      success: (result) => resolve(!!result.confirm),
      fail: () => resolve(false),
    });
  });
  if (!confirmed) return;

  submitting.value = true;
  try {
    const response = await issueMemberCard(session.currentSiteId, memberId.value, payload);
    uni.showToast({ title: "发卡成功", icon: "success" });
    setTimeout(() => {
      uni.redirectTo({
        url: `/pages/members/card-detail?memberId=${memberId.value}&memberCardId=${response.data.id}`,
      });
    }, 400);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "发卡失败", icon: "none" });
  } finally {
    submitting.value = false;
  }
}

onLoad((options) => {
  memberId.value = Number(options?.memberId ?? options?.id);
});

onShow(async () => {
  if (await requireStaffAuth()) await loadPage();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container detail-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <template v-else-if="member">
      <view class="section-band">
        <view class="section-heading">发卡会员</view>
        <view class="meta">{{ member.name }} · {{ member.memberNo }}</view>
      </view>

      <view class="section-band">
        <view class="section-heading">卡产品</view>
        <view class="picker-row" @click="chooseProduct">
          <text v-if="selectedProduct" class="picker-value">{{ selectedProduct.name }}</text>
          <text v-else class="picker-placeholder">选择要发放的卡产品</text>
          <u-icon name="arrow-right" size="16" color="#bfbfbf" />
        </view>
        <view v-if="selectedProduct" class="meta">{{ productSummary(selectedProduct) }}</view>
        <view v-if="selectedProduct" class="section-hint">{{ productHint(selectedProduct) }}</view>
      </view>

      <view v-if="selectedProduct && (isStoredValue || isCount)" class="section-band">
        <view class="section-heading">开卡额度（可选）</view>
        <u-input
          v-if="isStoredValue"
          v-model="openingBalance"
          type="digit"
          placeholder="开卡金额，留空使用产品默认值"
        />
        <u-input
          v-if="isCount"
          v-model="openingCount"
          type="number"
          placeholder="开卡次数，留空使用产品默认值"
        />
      </view>

      <view v-if="selectedProduct" class="section-band">
        <view class="section-heading">售卡收款</view>
        <view class="sale-row">
          <text>真实实付</text>
          <view class="amount-entry"><text>¥</text><input v-model="actualAmount" type="digit" placeholder="0.00" /></view>
        </view>
        <view class="sale-row" @tap="choosePaymentMethod">
          <text>支付方式</text>
          <view class="row-value"><text>{{ paymentMethod === "balance" ? "会员余额" : "线上已收款（人工确认）" }}</text><u-icon name="arrow-right" size="15" color="#989898" /></view>
        </view>
        <view v-if="paymentMethod === 'balance' && wallet" class="section-hint">会员钱包余额 ¥{{ wallet.balance }}，最终扣款与防超扣由服务端原子处理</view>
        <view v-else-if="paymentMethod === 'balance'" class="section-hint">当前账号无钱包查看权限，余额与防超扣由服务端校验</view>
        <view v-else class="section-hint">请先确认款项已在线收取；这里只做人工记账，不会拉起微信支付，并以当前登录员工身份留痕。</view>
      </view>

      <view v-if="selectedProduct && canConfigureShareAssignments && shareRoles.length" class="section-band">
        <view class="section-heading">B 类型分成归属（可选）</view>
        <ShareAssignmentEditor
          v-model="shareAssignments"
          :roles="shareRoles"
          :staff="staffOptions"
          :staff-role-ids="staffRoleIds"
        />
        <view class="section-hint">归属会随售卡写入快照，后续耗卡由后端累计 B 提成</view>
      </view>

      <view class="section-band">
        <view class="section-heading">收款说明（必填）</view>
        <u-input v-model="reason" placeholder="至少 4 个字，例如：员工端新购卡" maxlength="500" />
        <view class="section-hint">将作为人工收款与财务审计留痕</view>
      </view>

      <view class="section-band">
        <u-button type="primary" :loading="submitting" :disabled="!selectedProduct" @click="submit">确认发卡</u-button>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.detail-page { padding-bottom: 48rpx; }
.section-band { margin-top: 16rpx; padding: 28rpx 24rpx; background: $color-surface; border-radius: 20rpx; }
.section-heading { font-size: 30rpx; font-weight: 600; }
.section-hint, .meta { margin-top: 8rpx; color: $color-text-secondary; font-size: 24rpx; }
.picker-row { display: flex; align-items: center; justify-content: space-between; margin-top: 20rpx; padding: 24rpx; background: $color-page; border: 1rpx solid $color-border; border-radius: 12rpx; }
.picker-value { font-size: 28rpx; }
.picker-placeholder { color: $color-text-secondary; font-size: 28rpx; }
.sale-row { display: flex; align-items: center; justify-content: space-between; min-height: 82rpx; border-bottom: 1rpx solid #f1f1f1; font-size: 26rpx; }
.sale-row:last-of-type { border-bottom: 0; }
.amount-entry, .row-value { display: flex; align-items: center; gap: 8rpx; color: $color-text-secondary; }
.amount-entry input { width: 180rpx; text-align: right; }
</style>
