<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{ value: string | number }>();

const labels: Record<string, string> = {
  active: "启用",
  adjusted: "已调整",
  archived: "已归档",
  absent: "缺席",
  cancelled: "已取消",
  closed: "已关闭",
  closing: "关单中",
  completed: "已完成",
  confirmed: "已预约",
  departed: "已离职",
  disabled: "已停用",
  exhausted: "已用尽",
  ended: "已结束",
  expired: "已过期",
  final: "已结算",
  frozen: "冻结",
  lead: "潜客",
  paid: "已支付",
  open: "待关账",
  pending_activation: "待激活",
  pending_payment: "待支付",
  scheduled: "已排课",
  provisional: "暂计",
  reversed: "已冲正",
  superseded: "已替代",
  suspended: "已暂停",
  visitor: "访客",
  voided: "已作废",
  waitlisted: "候补",
};

const label = computed(() => labels[String(props.value)] ?? props.value);

const tone = computed(() => {
  const value = String(props.value);
  if (/active|paid|completed|confirmed|scheduled|启用|活跃|营业|成功|支付|生效|更新|在职|完成/.test(value)) return "success";
  if (/pending|closing|waitlisted|open|provisional|adjusted|待|候补|到期|预约|未开始|暂计|调整/.test(value)) return "warning";
  if (/disabled|cancelled|absent|closed|frozen|voided|departed|expired|exhausted|ended|superseded|reversed|停|冻结|失败|作废|离职|替代|冲正/.test(value)) return "danger";
  return "neutral";
});
</script>

<template><span class="status-pill" :class="`status-${tone}`"><i />{{ label }}</span></template>
