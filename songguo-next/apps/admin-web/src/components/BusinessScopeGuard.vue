<script setup lang="ts">
import { OfficeBuilding, Warning } from "@element-plus/icons-vue";

import { useBusinessScopeStore } from "@/stores/businessScope";

defineProps<{ writeHint?: string }>();

const scope = useBusinessScopeStore();
</script>

<template>
  <div class="business-scope-guard" :class="{ ready: scope.ready }">
    <el-icon><OfficeBuilding v-if="scope.ready" /><Warning v-else /></el-icon>
    <div>
      <strong>{{ scope.ready ? scope.scopeLabel : "尚未选择业务作用域" }}</strong>
      <span v-if="scope.ready">{{ writeHint ?? "所有查询与命令均限定在当前租户和场馆。" }}</span>
      <span v-else>请在顶部先选择租户和场馆；未选择时所有写操作都已禁用。</span>
    </div>
    <slot :ready="scope.ready" />
  </div>
</template>
