<script setup lang="ts">
import { computed } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

const session = useSessionStore();

const canBatchImport = computed(() => session.can("crm.member.batch-import") || session.can("crm.member.create"));
const canFilter = computed(() => session.can("crm.member.read"));

type BatchAction = {
  id: string;
  title: string;
  desc: string;
  enabled: boolean;
  reason?: string;
  action: () => void;
};

const actions = computed<BatchAction[]>(() => [
  {
    id: "filter-batch",
    title: "按条件筛选后批量处理",
    desc: "进入筛选页选择会员群体，再进行后续操作",
    enabled: canFilter.value,
    reason: "需要会员查看权限",
    action: () => uni.navigateTo({ url: "/subpackages/members/filter" }),
  },
  {
    id: "import",
    title: "文本批量导入潜客",
    desc: "粘贴「手机号+姓名」批量录入，走添加链路",
    enabled: canBatchImport.value,
    reason: "需要批量导入或创建权限",
    action: () => uni.navigateTo({ url: "/subpackages/members/batch-import" }),
  },
  {
    id: "link-requests",
    title: "档案关联审核",
    desc: "处理会员手机号关联申请",
    enabled: canFilter.value,
    reason: "需要会员查看权限",
    action: () => uni.navigateTo({ url: "/subpackages/members/link-requests" }),
  },
  {
    id: "archived-cards",
    title: "归档会员卡",
    desc: "查看已归档会员卡并可恢复",
    enabled: canFilter.value,
    reason: "需要会员查看权限",
    action: () => uni.navigateTo({ url: "/subpackages/members/archived-cards/index" }),
  },
  {
    id: "batch-issue",
    title: "批量发卡",
    desc: "对选中会员统一发卡",
    enabled: false,
    reason: "后端暂无批量发卡 API",
    action: () => uni.showToast({ title: "批量发卡接口未就绪", icon: "none" }),
  },
  {
    id: "batch-balance",
    title: "批量调整余额",
    desc: "勾选会员后，对其第一张可操作卡统一增减余额",
    enabled: session.can("member-card.balance.adjust"),
    reason: "需要会员卡余额调整权限",
    action: () => uni.navigateTo({ url: "/subpackages/members/batch-workbench?action=balance" }),
  },
  {
    id: "batch-validity",
    title: "批量延期",
    desc: "勾选会员后，对其第一张可操作卡统一延长有效期",
    enabled: session.can("member-card.validity.extend"),
    reason: "需要会员卡延期权限",
    action: () => uni.navigateTo({ url: "/subpackages/members/batch-workbench?action=validity" }),
  },
  {
    id: "batch-freeze",
    title: "批量停卡/复卡",
    desc: "勾选会员后，对其第一张可操作卡统一冻结或解冻",
    enabled: session.can("member-card.freeze"),
    reason: "需要会员卡冻结权限",
    action: () => uni.navigateTo({ url: "/subpackages/members/batch-workbench?action=freeze" }),
  },
]);

function runAction(item: BatchAction) {
  if (!item.enabled) {
    uni.showToast({ title: item.reason || "暂不可用", icon: "none" });
    return;
  }
  item.action();
}

onShow(async () => {
  await requireStaffAuth();
});
</script>

<template>
  <view class="page-container batch-page">
    <view class="page-title">批量操作</view>
    <view class="page-hint">
      原版「批量」进入筛选批量工作台。文本导入请使用下方「文本批量导入」，也可从新增会员页进入。
    </view>

    <view
      v-for="item in actions"
      :key="item.id"
      class="action-row"
      :class="{ disabled: !item.enabled }"
      @tap="runAction(item)"
    >
      <view class="action-main">
        <text class="action-title">{{ item.title }}</text>
        <text class="action-desc">{{ item.desc }}</text>
        <text v-if="!item.enabled && item.reason" class="action-block">{{ item.reason }}</text>
      </view>
      <u-icon name="arrow-right" size="16" :color="item.enabled ? '#989898' : '#d8d8d8'" />
    </view>
  </view>
</template>

<style scoped lang="scss">
.batch-page {
  padding-bottom: 48rpx;
}

.page-title {
  font-size: 36rpx;
  font-weight: 600;
}

.page-hint {
  margin-top: 10rpx;
  margin-bottom: 24rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
  line-height: 1.5;
}

.action-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16rpx;
  margin-bottom: 16rpx;
  padding: 28rpx 24rpx;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-sm;
}

.action-row.disabled {
  opacity: 0.72;
}

.action-main {
  flex: 1;
  min-width: 0;
}

.action-title {
  display: block;
  color: $color-text;
  font-size: 28rpx;
  font-weight: 600;
}

.action-desc {
  display: block;
  margin-top: 8rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
  line-height: 1.4;
}

.action-block {
  display: block;
  margin-top: 10rpx;
  color: #ed920f;
  font-size: 22rpx;
}
</style>
