<script setup lang="ts">
import { MoreFilled } from "@element-plus/icons-vue";

import type { ResourceAction, ResourceColumn } from "@/types/admin";
import StatusPill from "./StatusPill.vue";

defineProps<{
  columns: ResourceColumn[];
  rows: Array<Record<string, string | number>>;
  actions: ResourceAction[];
  can: (permission?: string) => boolean;
}>();

function displayValue(value: string | number, type?: ResourceColumn["type"]) {
  if (type === "money" && typeof value === "number") {
    return new Intl.NumberFormat("zh-CN", { style: "currency", currency: "CNY" }).format(value);
  }
  return value;
}
</script>

<template>
  <el-table :data="rows" class="resource-table" row-key="id">
    <el-table-column type="selection" width="46" />
    <el-table-column
      v-for="column in columns"
      :key="column.key"
      :prop="column.key"
      :label="column.label"
      :width="column.width"
      :min-width="column.minWidth"
    >
      <template #default="scope">
        <StatusPill v-if="column.type === 'status'" :value="scope.row[column.key]" />
        <span v-else-if="column.key === 'name' || column.key === 'member'" class="primary-cell">
          <b>{{ displayValue(scope.row[column.key], column.type) }}</b>
          <small>{{ scope.row.id }}</small>
        </span>
        <span v-else>{{ displayValue(scope.row[column.key], column.type) }}</span>
      </template>
    </el-table-column>
    <el-table-column label="操作" fixed="right" width="150" align="right">
      <template #default>
        <div class="row-actions">
          <button
            v-for="action in actions.filter((item) => can(item.permission)).slice(0, 2)"
            :key="action.key"
            type="button"
            :class="{ danger: action.danger }"
          >
            {{ action.label }}
          </button>
          <button type="button" class="more-action"><el-icon><MoreFilled /></el-icon></button>
        </div>
      </template>
    </el-table-column>
  </el-table>
</template>
