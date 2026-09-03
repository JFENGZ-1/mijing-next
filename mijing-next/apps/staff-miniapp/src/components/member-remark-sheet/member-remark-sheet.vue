<script setup lang="ts">
/** 对标原版 pageMember/components/remarks：编辑备注底部弹层 */
import { computed, ref, watch } from "vue";
import FfBottomSheet from "@/components/ff-bottom-sheet/ff-bottom-sheet.vue";

const props = defineProps<{
  show: boolean;
  value?: string | null;
}>();

const emit = defineEmits<{
  (e: "update:show", value: boolean): void;
  (e: "confirm", value: string): void;
}>();

const remarksText = ref("");
const charCount = computed(() => remarksText.value.length);

watch(
  () => props.show,
  (visible) => {
    if (visible) remarksText.value = props.value || "";
  },
);

function clearText() {
  remarksText.value = "";
}

function onConfirm() {
  emit("confirm", remarksText.value);
}
</script>

<template>
  <FfBottomSheet
    :show="show"
    title="编辑备注"
    :height-rpx="867"
    confirm-text="确　定"
    flush-body
    @update:show="emit('update:show', $event)"
    @confirm="onConfirm"
  >
    <view class="main-box">
      <view class="form-box">
        <textarea
          v-model="remarksText"
          class="remark-textarea"
          maxlength="200"
          placeholder="仅管理员可见，会员不会看到此备注"
          placeholder-style="color:#d0d2d7;"
        />
        <view class="btn-row">
          <view class="eliminate" @tap="clearText">清除</view>
          <view class="count-title">已写{{ charCount }}字/ 最多200字</view>
        </view>
      </view>
      <view class="spacer" />
    </view>
  </FfBottomSheet>
</template>

<style scoped lang="scss">
.main-box {
  width: 100%;
}

.form-box {
  padding: 27rpx 31rpx 25rpx;
}

.remark-textarea {
  display: block;
  box-sizing: border-box;
  width: 689rpx;
  height: 400rpx !important;
  margin: 0 auto 16rpx;
  padding: 20rpx 28rpx;
  background: #fff;
  border: 1px solid #dadada;
  border-radius: 22rpx;
  color: #989898;
  font-size: 28rpx;
  font-weight: 400;
  line-height: 42rpx;
}

.btn-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 28rpx 0 0;
}

.eliminate {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 118rpx;
  height: 43rpx;
  background: #fdf6de;
  border-radius: 21rpx;
  color: #e98933;
  font-size: 21rpx;
}

.count-title {
  color: #989898;
  font-size: 25rpx;
  text-align: right;
}

.spacer {
  padding-top: 160rpx;
}
</style>
