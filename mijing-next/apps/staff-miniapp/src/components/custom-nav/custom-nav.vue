<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    text: string;
    bg?: string;
    /** 对标原版 navigation headUrl：标题左侧小头像 */
    headUrl?: string;
  }>(),
  { bg: "#FBD128", headUrl: "" },
);

const emit = defineEmits<{
  (event: "back"): void;
}>();

const statusBarHeight = uni.getSystemInfoSync().statusBarHeight ?? 20;

const customBarHeight = computed(() => {
  try {
    const menu = uni.getMenuButtonBoundingClientRect();
    return menu.height + (menu.top - statusBarHeight) * 2;
  } catch {
    return 44;
  }
});

const totalHeight = computed(() => statusBarHeight + customBarHeight.value);

function onBack() {
  emit("back");
  const pages = getCurrentPages();
  if (pages.length > 1) {
    uni.navigateBack();
  } else {
    uni.switchTab({ url: "/pages/settings/hub/index" });
  }
}
</script>

<template>
  <view class="cu-custom" :style="{ height: `${totalHeight}px`, background: props.bg }">
    <view :style="{ height: `${totalHeight}px` }">
      <view class="cu-status" :style="{ height: `${statusBarHeight}px` }" />
      <view class="cu-title" :style="{ height: `${customBarHeight}px` }">
        <view class="title-center">
          <image v-if="headUrl" class="head-avatar" :src="headUrl" mode="aspectFill" />
          <text class="text">{{ text }}</text>
        </view>
        <view class="back-wrap" @tap="onBack">
          <u-icon name="arrow-left" size="18" color="#181818" />
        </view>
      </view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.cu-custom {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  z-index: 199;
}

.cu-title {
  position: relative;
  z-index: 199;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.title-center {
  display: flex;
  align-items: center;
  justify-content: center;
  max-width: 60%;
}

.head-avatar {
  width: 40rpx;
  height: 40rpx;
  margin-right: 10rpx;
  border-radius: 50%;
  flex-shrink: 0;
}

.text {
  overflow: hidden;
  color: #181818;
  font-size: 16px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.back-wrap {
  position: absolute;
  top: 0;
  left: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 75rpx;
  height: 100%;
}
</style>
