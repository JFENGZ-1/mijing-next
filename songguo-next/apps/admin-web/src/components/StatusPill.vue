<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{ value: string | number }>();

const tone = computed(() => {
  const value = String(props.value);
  if (/启用|活跃|营业|成功|支付|生效|更新|在职|完成/.test(value)) return "success";
  if (/待|候补|到期|预约|未开始/.test(value)) return "warning";
  if (/停|冻结|失败|作废|离职/.test(value)) return "danger";
  return "neutral";
});
</script>

<template><span class="status-pill" :class="`status-${tone}`"><i />{{ value }}</span></template>
