<script setup lang="ts">
withDefaults(
  defineProps<{
    /** 1=浅色底 logo（默认）；0=深色底宽 logo */
    dataType?: "0" | "1";
  }>(),
  { dataType: "1" },
);
</script>

<template>
  <view class="bottom-logo">
    <image
      v-if="dataType === '1'"
      class="logo"
      src="/static/imgs/startlogo.png"
      mode="aspectFit"
    />
    <image
      v-else
      class="logo logo-wide"
      src="/static/imgs/startlogo.png"
      mode="aspectFit"
    />
  </view>
</template>

<style scoped lang="scss">
.bottom-logo {
  display: flex;
  justify-content: center;
  padding: 70rpx 0 88rpx;
  background: #f5f5f5;
}

.logo {
  width: 182rpx;
  height: 67rpx;
}

.logo-wide {
  width: 214rpx;
  height: 86rpx;
}
</style>
