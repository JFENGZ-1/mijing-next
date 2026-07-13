<script setup lang="ts">
withDefaults(defineProps<{ text?: string }>(), { text: "松果瑜伽 · 让练习成为日常" });
</script>

<template>
  <view class="bottom-logo">
    <view class="logo-line" />
    <view class="logo-text">{{ text }}</view>
    <view class="logo-line" />
  </view>
</template>

<style scoped lang="scss">
.bottom-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 18rpx;
  padding: 60rpx 0 40rpx;
}

.logo-line {
  width: 60rpx;
  height: 1rpx;
  background: #d8d8d8;
}

.logo-text {
  color: #bfbfbf;
  font-size: 22rpx;
  letter-spacing: 1rpx;
}
</style>
