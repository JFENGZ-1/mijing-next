<script setup lang="ts">
/** 对标原版 components/ff-popup：固定高度、标题、黄底 tips、内容滚动、底部确定 */
withDefaults(
  defineProps<{
    show: boolean;
    title: string;
    tips?: string;
    /** 弹层总高度（rpx），原版写备注 1100、修改预约 1280 */
    heightRpx?: number;
    confirmText?: string;
    confirmDisabled?: boolean;
    showConfirm?: boolean;
    /** 去掉左右内边距，便于内部整块居中（如修改预约） */
    flushBody?: boolean;
    titleAlign?: "center" | "left";
  }>(),
  {
    tips: "",
    heightRpx: 1100,
    confirmText: "确　定",
    confirmDisabled: false,
    showConfirm: true,
    flushBody: false,
    titleAlign: "center",
  },
);

const emit = defineEmits<{
  (event: "update:show", value: boolean): void;
  (event: "close"): void;
  (event: "confirm"): void;
}>();

function handleClose() {
  emit("update:show", false);
  emit("close");
}
</script>

<template>
  <u-popup
    :show="show"
    mode="bottom"
    round="20"
    :closeable="true"
    :safe-area-inset-bottom="true"
    :z-index="10080"
    @close="handleClose"
  >
    <view class="ff-sheet" :style="{ height: `${heightRpx}rpx` }">
      <view class="ff-sheet-title" :class="{ 'ff-sheet-title--left': titleAlign === 'left' }">{{ title }}</view>
      <scroll-view scroll-y class="ff-sheet-scroll" :enhanced="true" :show-scrollbar="false">
        <slot name="tips">
          <view v-if="tips" class="ff-sheet-tips">
            <u-icon name="bell" size="13" color="#d76418" />
            <text class="ff-sheet-tips-text">{{ tips }}</text>
          </view>
        </slot>
        <view class="ff-sheet-body" :class="{ 'ff-sheet-body--flush': flushBody }">
          <slot />
        </view>
      </scroll-view>
      <view v-if="showConfirm" class="ff-sheet-foot">
        <button
          class="ff-sheet-confirm"
          :disabled="confirmDisabled"
          @tap="emit('confirm')"
        >{{ confirmText }}</button>
      </view>
    </view>
  </u-popup>
</template>

<style scoped lang="scss">
.ff-sheet {
  position: relative;
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  padding-bottom: env(safe-area-inset-bottom);
  background: #fff;
  border-radius: 20rpx 20rpx 0 0;
}

.ff-sheet-title {
  flex-shrink: 0;
  padding: 0 34rpx;
  color: #000;
  font-size: 36rpx;
  font-weight: 500;
  line-height: 110rpx;
  text-align: center;

  &--left {
    text-align: left;
  }
}

.ff-sheet-scroll {
  flex: 1;
  min-height: 0;
  width: 100%;
}

.ff-sheet-tips {
  display: flex;
  align-items: flex-start;
  gap: 8rpx;
  box-sizing: border-box;
  width: 694rpx;
  margin: 0 auto 20rpx;
  padding: 17rpx 20rpx;
  background: #fff9db;
  border-radius: 14rpx;
  color: #d76418;
  font-size: 22rpx;
  line-height: 32rpx;
}

.ff-sheet-tips-text {
  flex: 1;
}

.ff-sheet-body {
  padding: 0 28rpx 24rpx;
}

.ff-sheet-body--flush {
  padding-left: 0;
  padding-right: 0;
}

.ff-sheet-foot {
  flex-shrink: 0;
  padding: 11rpx 28rpx 20rpx;
}

.ff-sheet-confirm {
  width: 100%;
  height: 83rpx;
  line-height: 83rpx;
  background: #fbd128;
  border-radius: 42rpx;
  color: #181818;
  font-size: 32rpx;
  font-weight: 500;

  &[disabled] {
    opacity: 0.6;
    background: #fbd128;
    color: #181818;
  }
}

.ff-sheet-confirm::after {
  border: 0;
}
</style>

<style lang="scss">
/* 对标原版 ff-popup 关闭钮：灰圆底 */
.u-popup__content__close--top-right,
.u-popup .u-popup__content__close {
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  top: 20rpx !important;
  width: 47rpx !important;
  height: 47rpx !important;
  background: #f5f5f5 !important;
  border-radius: 50% !important;
}
</style>
