<script setup lang="ts">
/** 对标原版领卡通知：扫码 / 转发 / 会员自领取 */
import { computed, ref, watch } from "vue";
import { createMemberCardTransferShareToken } from "@/api/member-cards";
import { useSessionStore } from "@/stores/session";
import type { StaffMemberCardSummary } from "@/types/crm";

const props = defineProps<{
  show: boolean;
  memberId: number | null;
  memberName?: string;
  memberMobile?: string | null;
  cards: StaffMemberCardSummary[];
}>();

const emit = defineEmits<{
  (e: "update:show", value: boolean): void;
}>();

const session = useSessionStore();
const loading = ref(false);
const token = ref("");
const expiresAt = ref("");
const showCopyModal = ref(false);
const selectedCardId = ref<number | null>(null);

const claimableCards = computed(() =>
  props.cards.filter((c) => c.status !== "archived" && c.status !== "voided"),
);

const selectedCard = computed(
  () => claimableCards.value.find((c) => c.id === selectedCardId.value) ?? claimableCards.value[0] ?? null,
);

const claimPath = computed(() => {
  if (!token.value) return "";
  return `/pages/cards/transfer?token=${token.value}`;
});

const copyText = computed(() => {
  const phone = props.memberMobile || "你的手机号";
  return [
    "请打开「会员约课」小程序领取会员卡",
    `领取口令：${token.value || "—"}`,
    `手机号：${phone}`,
    props.memberName ? `会员：${props.memberName}` : "",
  ]
    .filter(Boolean)
    .join("\n");
});

watch(
  () => props.show,
  async (visible) => {
    if (!visible) {
      token.value = "";
      showCopyModal.value = false;
      return;
    }
    selectedCardId.value = claimableCards.value[0]?.id ?? null;
    await loadToken();
  },
);

async function loadToken() {
  if (!session.currentSiteId || !selectedCard.value) {
    token.value = "";
    return;
  }
  loading.value = true;
  try {
    const res = await createMemberCardTransferShareToken(session.currentSiteId, selectedCard.value.id);
    token.value = res.data.token;
    expiresAt.value = res.data.expiresAt;
  } catch (error) {
    token.value = "";
    uni.showToast({
      title: error instanceof Error ? error.message : "领取码生成失败",
      icon: "none",
    });
  } finally {
    loading.value = false;
  }
}

async function onPickCard(card: StaffMemberCardSummary) {
  selectedCardId.value = card.id;
  await loadToken();
}

function close() {
  emit("update:show", false);
}

function copyPath() {
  if (!token.value) {
    uni.showToast({ title: "暂无领取码", icon: "none" });
    return;
  }
  uni.setClipboardData({
    data: claimPath.value || token.value,
    success: () => uni.showToast({ title: "已复制领取路径", icon: "none" }),
  });
}

function openSelfReceive() {
  if (!token.value) {
    uni.showToast({ title: "暂无领取码", icon: "none" });
    return;
  }
  showCopyModal.value = true;
}

function copyAndClose() {
  uni.setClipboardData({
    data: copyText.value,
    success: () => {
      showCopyModal.value = false;
      emit("update:show", false);
      uni.showToast({ title: "已复制", icon: "none" });
    },
  });
}
</script>

<template>
  <u-popup :show="show" mode="center" round="16" :closeable="true" @close="close">
    <view class="claim-modal">
      <view class="title">会员领取会员卡的三种方式</view>

      <view v-if="claimableCards.length > 1" class="card-pick">
        <view
          v-for="card in claimableCards"
          :key="card.id"
          class="pick-item"
          :class="{ active: selectedCard?.id === card.id }"
          @tap="onPickCard(card)"
        >
          {{ card.name || card.cardNo }}
        </view>
      </view>

      <view class="content_qr_code">
        <view class="qr_code">
          <u-loading-icon v-if="loading" mode="circle" color="#FBD128" />
          <view v-else class="qr-fallback">
            <u-icon name="scan" size="48" color="#181818" />
            <text class="token-text">{{ token ? token.slice(0, 8) + "…" : "暂无领取码" }}</text>
          </view>
        </view>
        <view class="qr_code_name">让会员扫码领取</view>
        <view v-if="expiresAt" class="expire">有效至 {{ expiresAt.slice(0, 16).replace("T", " ") }}</view>
        <view class="copy-link" @tap="copyPath">复制领取路径</view>
      </view>

      <view class="border" />

      <view class="receive">
        <button class="forward" open-type="share" :disabled="!token">
          <view class="forward_image">
            <u-icon name="share-fill" size="28" color="#FBD128" />
          </view>
          <view class="forward_name">转发给会员</view>
        </button>
        <view class="self_receive" @tap="openSelfReceive">
          <view class="self_receive_image">
            <u-icon name="file-text" size="28" color="#FBD128" />
          </view>
          <view class="self_receive_name">会员自领取</view>
        </view>
      </view>

      <view class="Cancel">
        <view class="modal-btn" @tap="close">关闭</view>
      </view>
    </view>
  </u-popup>

  <u-modal
    :show="showCopyModal"
    title="可将该文字发给会员"
    :show-cancel-button="false"
    confirm-text="复制并关闭"
    @confirm="copyAndClose"
    @close="showCopyModal = false"
  >
    <view class="explain">
      <text class="explain_text">{{ copyText }}</text>
    </view>
  </u-modal>
</template>

<style scoped lang="scss">
.claim-modal {
  width: 620rpx;
  padding: 36rpx 28rpx 24rpx;
  box-sizing: border-box;
}

.title {
  margin-bottom: 28rpx;
  color: #181818;
  font-size: 32rpx;
  font-weight: 600;
  text-align: center;
}

.card-pick {
  display: flex;
  flex-wrap: wrap;
  gap: 12rpx;
  margin-bottom: 20rpx;
}

.pick-item {
  padding: 8rpx 18rpx;
  border: 1rpx solid #dadada;
  border-radius: 24rpx;
  color: #989898;
  font-size: 22rpx;
}

.pick-item.active {
  border-color: #fbd128;
  background: #fff9db;
  color: #181818;
}

.content_qr_code {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.qr_code {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 280rpx;
  height: 280rpx;
  background: #f5f5f5;
  border-radius: 16rpx;
}

.qr-fallback {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12rpx;
}

.token-text {
  color: #7e7e7e;
  font-size: 22rpx;
}

.qr_code_name {
  margin-top: 16rpx;
  color: #181818;
  font-size: 26rpx;
}

.expire {
  margin-top: 8rpx;
  color: #989898;
  font-size: 20rpx;
}

.copy-link {
  margin-top: 12rpx;
  color: #ed920f;
  font-size: 24rpx;
}

.border {
  height: 1rpx;
  margin: 28rpx 0;
  background: #f0f0f0;
}

.receive {
  display: flex;
  justify-content: space-around;
  margin-bottom: 24rpx;
}

.forward,
.self_receive {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10rpx;
  margin: 0;
  padding: 0;
  background: transparent;
  border: 0;
  line-height: 1.2;
}

.forward::after {
  border: 0;
}

.forward_image,
.self_receive_image {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 88rpx;
  height: 88rpx;
  background: #fff9db;
  border-radius: 50%;
}

.forward_name,
.self_receive_name {
  color: #181818;
  font-size: 24rpx;
}

.Cancel {
  display: flex;
  justify-content: center;
}

.modal-btn {
  width: 100%;
  height: 80rpx;
  line-height: 80rpx;
  background: #fbd128;
  border-radius: 40rpx;
  color: #181818;
  font-size: 30rpx;
  text-align: center;
}

.explain {
  padding: 8rpx 4rpx 16rpx;
}

.explain_text {
  white-space: pre-wrap;
  color: #181818;
  font-size: 26rpx;
  line-height: 1.7;
}
</style>
