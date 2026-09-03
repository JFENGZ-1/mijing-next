<script setup lang="ts">
import { onShow } from "@dcloudio/uni-app";
import { requireStaffAuth } from "@/auth/guard";

onShow(async () => {
  await requireStaffAuth();
});
</script>

<template>
  <view class="page-container">
    <view class="section">
      <text class="section-title">1、如何启用连锁分店功能？</text>
      <text class="body">a）填写品牌名称及 LOGO；b）添加分店（至少 2 个）。在分店管理中满足以上两个条件后即可自动激活分店功能。</text>
    </view>
    <view class="section">
      <text class="section-title">2、连锁通用卡</text>
      <text class="body">发行多店通用卡并发放给分店会员后，持通用卡会员可跨店约课。分店不可编辑总店发行的通用卡，仅总店享有编辑权限。</text>
    </view>
    <view class="section">
      <text class="section-title">3、适用店与课</text>
      <text class="body">可指定通用卡适用哪些分店；选择支持某分店后，该分店可配置该卡可约哪些课程。由总店控制可用分店与课程范围。</text>
    </view>
    <view class="section">
      <text class="section-title">4、总店员工</text>
      <text class="body">在此添加的员工视为总店管理员，可进入所有分店并享有查看和编辑权限。仅馆长和总店员工可看到「总店・连锁管理」模块。</text>
    </view>
    <view class="section">
      <text class="section-title">5、售卡统计 / 课时费统计</text>
      <text class="body">售卡统计仅统计多店通用卡；课时费统计汇总全部教练实际上课节数。</text>
    </view>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f5f5f5; }
.section { margin-bottom: 24rpx; padding: 24rpx; background: #fff; border-radius: 16rpx; }
.section-title, .body { display: block; }
.section-title { margin-bottom: 12rpx; font-size: 30rpx; font-weight: 600; }
.body { color: #505050; font-size: 26rpx; line-height: 1.6; }
</style>
