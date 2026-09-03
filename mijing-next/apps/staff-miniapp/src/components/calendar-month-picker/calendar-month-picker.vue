<script setup lang="ts">
/** 对标原版 pageMember/components/calendar-month：按年分组的月份选择 */
import { computed, ref, watch } from "vue";
import FfBottomSheet from "@/components/ff-bottom-sheet/ff-bottom-sheet.vue";

const props = withDefaults(
  defineProps<{
    show: boolean;
    year: number;
    month: number;
    /** 往前可选多少年（含当前年） */
    yearSpan?: number;
  }>(),
  { yearSpan: 3 },
);

const emit = defineEmits<{
  (event: "update:show", value: boolean): void;
  (event: "change", payload: { year: number; month: number }): void;
}>();

const selectedYear = ref(props.year);
const selectedMonth = ref(props.month);

watch(
  () => [props.show, props.year, props.month] as const,
  ([show, year, month]) => {
    if (show) {
      selectedYear.value = year;
      selectedMonth.value = month;
    }
  },
);

interface MonthCell {
  text: string;
  year: number;
  month: number;
  value: string;
}

const calendarList = computed(() => {
  const now = new Date();
  const currentYear = now.getFullYear();
  const span = Math.max(1, props.yearSpan);
  const list: Array<{ year: number; months: MonthCell[] }> = [];
  for (let y = currentYear; y >= currentYear - span + 1; y -= 1) {
    list.push({
      year: y,
      months: Array.from({ length: 12 }, (_, i) => ({
        text: `${i + 1}月`,
        year: y,
        month: i + 1,
        value: `${y}-${i + 1}`,
      })),
    });
  }
  return list;
});

function isSelected(cell: MonthCell) {
  return cell.year === selectedYear.value && cell.month === selectedMonth.value;
}

function pick(cell: MonthCell) {
  selectedYear.value = cell.year;
  selectedMonth.value = cell.month;
  emit("change", { year: cell.year, month: cell.month });
  emit("update:show", false);
}

function onClose() {
  emit("update:show", false);
}
</script>

<template>
  <FfBottomSheet
    :show="show"
    title="选择月份"
    :height-rpx="868"
    :show-confirm="false"
    flush-body
    @update:show="emit('update:show', $event)"
    @close="onClose"
  >
    <view class="calendar-month">
      <view v-for="block in calendarList" :key="block.year" class="year-box">
        <view class="year">
          <u-line color="#DADADA" />
          <text class="place_holder">{{ block.year }}</text>
          <u-line color="#DADADA" />
        </view>
        <view class="month">
          <text
            v-for="(cell, index) in block.months"
            :key="cell.value"
            class="item_month"
            :class="{
              item_month_0: index % 4 === 0,
              item_month_long: cell.month >= 10,
              aa: isSelected(cell),
              bb: !isSelected(cell),
            }"
            @tap="pick(cell)"
          >{{ cell.text }}</text>
        </view>
      </view>
    </view>
  </FfBottomSheet>
</template>

<style scoped lang="scss">
.calendar-month {
  margin: 20rpx 40rpx 40rpx;
}
.year-box {
  padding-bottom: 48rpx;
}
.year {
  display: flex;
  align-items: center;
  margin: 0 50rpx;
}
.place_holder {
  color: #181818;
  font-size: 32rpx;
  margin: 0 32rpx;
  flex-shrink: 0;
}
.month {
  display: flex;
  flex-wrap: wrap;
  margin-top: 24rpx;
}
.item_month {
  box-sizing: border-box;
  color: #181818;
  font-size: 28rpx;
  margin-left: 40rpx;
  margin-top: 28rpx;
  padding: 11rpx 45rpx;
  text-align: center;
}
.item_month_0 {
  margin-left: 0;
  padding: 11rpx 45rpx;
}
.item_month_long {
  margin-left: 37rpx;
  padding: 11rpx 36rpx;
}
.aa {
  background: #22c788;
  border-radius: 35rpx;
  color: #fff;
  font-weight: 500;
  padding: 15rpx 45rpx;
}
.bb {
  color: #181818;
}
</style>
