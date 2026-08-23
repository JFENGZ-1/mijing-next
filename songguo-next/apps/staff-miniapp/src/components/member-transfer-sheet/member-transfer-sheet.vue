<script setup lang="ts">
/** 对标原版 pageMember/components/make-over：转让账号（新版用改手机号/姓名；冲突走失败弹窗） */
import { reactive, ref, watch } from "vue";
import { ApiError } from "@songguo/api-client";
import { updateCrmMember } from "@/api/crm";
import FfBottomSheet from "@/components/ff-bottom-sheet/ff-bottom-sheet.vue";
import { useSessionStore } from "@/stores/session";

const props = defineProps<{
  show: boolean;
  memberId: number | null;
  version: number | null;
}>();

const emit = defineEmits<{
  (e: "update:show", value: boolean): void;
  (e: "success"): void;
}>();

const session = useSessionStore();
const user = reactive({ mobile: "", name: "" });
const submitting = ref(false);
const showSuccess = ref(false);
const showFail = ref(false);

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      user.mobile = "";
      user.name = "";
      showSuccess.value = false;
      showFail.value = false;
    }
  },
);

async function onConfirm() {
  if (!user.mobile.trim()) {
    uni.showToast({ title: "请输入手机号", icon: "none" });
    return;
  }
  if (!/^1\d{10}$/.test(user.mobile.trim())) {
    uni.showToast({ title: "手机号格式不正确", icon: "none" });
    return;
  }
  if (!props.memberId || !props.version || !session.currentSiteId) {
    uni.showToast({ title: "会员参数无效", icon: "none" });
    return;
  }
  submitting.value = true;
  try {
    await updateCrmMember(session.currentSiteId, props.memberId, {
      version: props.version,
      mobile: user.mobile.trim(),
      ...(user.name.trim() ? { name: user.name.trim() } : {}),
    });
    showSuccess.value = true;
    emit("success");
  } catch (error) {
    if (error instanceof ApiError && (error.statusCode === 409 || String(error.payload?.code || "").includes("MOBILE"))) {
      showFail.value = true;
    } else {
      uni.showToast({
        title: error instanceof Error ? error.message : "转让失败",
        icon: "none",
      });
    }
  } finally {
    submitting.value = false;
  }
}

function closeSuccess() {
  showSuccess.value = false;
  emit("update:show", false);
}

function closeFail() {
  showFail.value = false;
}
</script>

<template>
  <FfBottomSheet
    :show="show"
    title="将此帐号转让给"
    tips="是指将此帐号手机号/姓名变更为其它会员资料；若该手机号已存在会员则不可转让"
    :height-rpx="720"
    confirm-text="确 定"
    :confirm-disabled="submitting"
    flush-body
    @update:show="emit('update:show', $event)"
    @confirm="onConfirm"
  >
    <view class="makeOver">
      <view class="textc">
        <text class="label"><text class="req">*</text>手机</text>
        <input
          v-model="user.mobile"
          class="field-input"
          type="number"
          maxlength="12"
          placeholder="输入手机号"
          placeholder-style="font-size:30rpx;color:#DADADA;"
        />
      </view>
      <view class="textc">
        <text class="label">姓名</text>
        <input
          v-model="user.name"
          class="field-input"
          type="text"
          maxlength="20"
          placeholder="输入姓名"
          placeholder-style="font-size:30rpx;color:#DADADA;"
        />
      </view>
    </view>
  </FfBottomSheet>

  <u-modal
    :show="showSuccess"
    title="操作成功！"
    :show-cancel-button="false"
    confirm-text="知道了"
    @confirm="closeSuccess"
    @close="closeSuccess"
  >
    <view class="modal-body">
      <view>1、通知该会员登录【会员约课端】即可</view>
      <view>2、帐号资料将变更到该手机号名下</view>
    </view>
  </u-modal>

  <u-modal
    :show="showFail"
    title="不可转让，场馆已经存在该会员！"
    :show-cancel-button="false"
    confirm-text="知道了"
    @confirm="closeFail"
    @close="closeFail"
  >
    <view class="modal-body">
      <view>您需要将原帐号<text class="danger">【删除后】才可以转让给她</text></view>
      <view>注：在会员页面顶部，搜索该会员手机号即可找到</view>
    </view>
  </u-modal>
</template>

<style scoped lang="scss">
.makeOver {
  margin: 50rpx 56rpx 90rpx;
  color: #181818;
  font-size: 28rpx;
  line-height: 28rpx;
}

.textc {
  display: flex;
  align-items: center;
  margin-bottom: 24rpx;
}

.label {
  width: 100rpx;
  flex-shrink: 0;
  color: #181818;
  font-size: 28rpx;
}

.req {
  margin-right: 2rpx;
  color: #dc3c5c;
  font-size: 30rpx;
}

.field-input {
  flex: 1;
  height: 90rpx;
  margin: 10rpx 20rpx;
  padding-left: 28rpx;
  background: #f5f5f5;
  border-radius: 30px;
  color: #7e7e7e;
  font-size: 30rpx;
}

.modal-body {
  padding: 8rpx 4rpx 16rpx;
  color: #181818;
  font-size: 26rpx;
  line-height: 1.8;
}

.danger {
  color: #dc3c5c;
}
</style>
