<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{
  label: string;
  value: string;
  trend: string;
  note: string;
  tone?: "green" | "amber" | "blue" | "dark";
}>();

const positive = computed(() => props.trend.startsWith("+"));
</script>

<template>
  <article class="metric-card" :class="`tone-${tone ?? 'green'}`">
    <div class="metric-label"><span>{{ label }}</span><i /></div>
    <strong>{{ value }}</strong>
    <div class="metric-foot">
      <span :class="{ positive }">{{ trend }}</span>
      <small>{{ note }}</small>
    </div>
  </article>
</template>
