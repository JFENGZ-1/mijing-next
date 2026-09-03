<script setup lang="ts">
/** 对标原版 pageMember/components/mark-pop：编辑标记（色旗单选） */
import { ref, watch } from "vue";
import FfBottomSheet from "@/components/ff-bottom-sheet/ff-bottom-sheet.vue";

export type MemberMarkFlag = 0 | 1 | 2 | 3 | 4 | 5;

const FLAG_OPTIONS: Array<{ status: MemberMarkFlag; color: string; label: string }> = [
  { status: 1, color: "#DC3C5C", label: "红色" },
  { status: 2, color: "#F5A623", label: "黄色" },
  { status: 3, color: "#22C788", label: "绿色" },
  { status: 4, color: "#5FA3EA", label: "蓝色" },
  { status: 5, color: "#9B59B6", label: "紫色" },
  { status: 0, color: "#DADADA", label: "没有备注" },
];

const props = defineProps<{
  show: boolean;
  /** 当前标记值，0=无 */
  value?: number;
}>();

const emit = defineEmits<{
  (e: "update:show", value: boolean): void;
  (e: "confirm", value: MemberMarkFlag): void;
}>();

const flag = ref<MemberMarkFlag>(0);

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      const v = Number(props.value ?? 0);
      flag.value = ([0, 1, 2, 3, 4, 5].includes(v) ? v : 0) as MemberMarkFlag;
    }
  },
);

function selectFlag(status: MemberMarkFlag) {
  flag.value = status;
  emit("confirm", status);
  emit("update:show", false);
}
</script>

<template>
  <FfBottomSheet
    :show="show"
    title="编辑标记"
    :height-rpx="867"
    :show-confirm="false"
    flush-body
    @update:show="emit('update:show', $event)"
  >
    <template #tips>
      <view class="ff-sheet-tips mark-tips">
        <u-icon name="bell" size="13" color="#d76418" />
        <view class="ff-sheet-tips-text">
          用来快速标记会员，具体含义由店长自己定义
          <text class="br">例如，红色代表该会员有意购买私教，兰色代表请求退卡</text>
        </view>
      </view>
    </template>

    <view class="main-box">
      <view class="form-box">
        <view class="list">
          <view
            v-for="item in FLAG_OPTIONS"
            :key="item.status"
            class="item"
            @tap="selectFlag(item.status)"
          >
            <view class="flag">
              <view class="flag_name" :style="{ background: item.color }" />
              <view v-if="item.status === 0" class="remarks">没有备注</view>
            </view>
            <u-radio-group :model-value="flag" placement="row">
              <u-radio :name="item.status" shape="circle" activeColor="#FBD128" :disabled="false" />
            </u-radio-group>
          </view>
        </view>
      </view>
      <view class="spacer" />
    </view>
  </FfBottomSheet>
</template>

<style scoped lang="scss">
.mark-tips {
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

.br {
  display: block;
}

.form-box {
  padding: 60rpx 31rpx 25rpx;
}

.list {
  padding: 0 66rpx 0 58rpx;
}

.item {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 26rpx;
  padding: 0 0 26rpx;
}

.item::after {
  content: " ";
  position: absolute;
  top: -50%;
  left: -50%;
  z-index: 1;
  box-sizing: border-box;
  width: 200%;
  height: 200%;
  border-bottom: 1px solid #f0f0f0;
  pointer-events: none;
  transform: scale(0.5);
}

.flag {
  display: flex;
  align-items: center;
  padding-left: 19rpx;
}

.flag_name {
  width: 42rpx;
  height: 42rpx;
  border-radius: 6rpx 6rpx 6rpx 0;
  clip-path: polygon(0 0, 100% 0, 100% 70%, 50% 100%, 0 70%);
}

.remarks {
  padding-left: 15rpx;
  color: #dadada;
  font-size: 28rpx;
}

.spacer {
  padding-top: 160rpx;
}
</style>
