<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import {
  fetchAllCompensationRoles,
  fetchCourseCompensationRule,
  updateCourseCompensationRule,
} from "@/api/compensation";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CompensationRole, CourseCompensationRule } from "@/types/compensation";
import { createCommandKey } from "@/utils/command-key";
import { confirmFinancePublish } from "@/utils/finance-publish-confirm";

interface RateDraft {
  role: CompensationRole;
  percent: string;
}

const session = useSessionStore();
const courseId = ref(0);
const courseName = ref("");
const loading = ref(true);
const saving = ref(false);
const loadError = ref("");
const roles = ref<CompensationRole[]>([]);
const rule = ref<CourseCompensationRule | null>(null);
const deliveryRoleId = ref<number | null>(null);
const sessionFee = ref("0.00");
const effectiveFrom = ref("");
const rateDrafts = ref<RateDraft[]>([]);
const canWrite = computed(() => session.can("compensation.rule.write"));
const retiredRoleRates = computed(() => {
  const activeIds = new Set(roles.value.map((role) => role.id));
  return (rule.value?.roleRates ?? []).filter((rate) => !activeIds.has(rate.roleId));
});

function today() {
  const now = new Date();
  const offset = now.getTimezoneOffset() * 60_000;
  return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}

async function load() {
  if (!session.currentSiteId || !courseId.value) return;
  loading.value = true;
  loadError.value = "";
  try {
    const [roleResponse, currentRule] = await Promise.all([
      fetchAllCompensationRoles(session.currentSiteId),
      fetchCourseCompensationRule(session.currentSiteId, courseId.value),
    ]);
    roles.value = roleResponse.filter((item) => item.status === "active");
    rule.value = currentRule;
    // Keep reading the legacy field for API compatibility. Actual A-role delivery
    // ownership is configured per schedule session, not as a course-wide default.
    deliveryRoleId.value = currentRule?.deliveryRoleId ?? null;
    sessionFee.value = currentRule?.sessionFee ?? "0.00";
    effectiveFrom.value = currentRule?.effectiveFrom?.slice(0, 10) ?? today();
    const existingRates = new Map((currentRule?.roleRates ?? []).map((item) => [item.roleId, item.rateBasisPoints]));
    rateDrafts.value = roles.value.map((role) => ({
      role,
      percent: existingRates.has(role.id) ? String((existingRates.get(role.id) ?? 0) / 100) : "0",
    }));
  } catch (error) {
    loadError.value = error instanceof Error ? error.message : "规则加载失败";
  } finally {
    loading.value = false;
  }
}

function setEffectiveFrom(event: { detail: { value: string } }) {
  effectiveFrom.value = event.detail.value;
}

function percentLabel(basisPoints: number): string {
  return `${Number((basisPoints / 100).toFixed(2))}%`;
}

function buildChangeSummary(
  nextFee: string,
  nextRates: Array<{ roleId: number; rateBasisPoints: number }>,
): { changes: string[]; warning?: string } {
  if (!rule.value) {
    const initialRates = nextRates.map((rate) => {
      const role = roles.value.find((item) => item.id === rate.roleId);
      const roleLabel = role ? `${role.type === "delivery" ? "A" : "B"}·${role.name}` : `角色 #${rate.roleId}`;
      return `${roleLabel}：未配置 → ${percentLabel(rate.rateBasisPoints)}`;
    });
    return {
      changes: [
        `课时费：未配置 → ¥${nextFee}/节`,
        `生效日：${effectiveFrom.value}`,
        ...initialRates,
      ],
    };
  }

  const changes: string[] = [];
  const currentFee = Number(rule.value.sessionFee).toFixed(2);
  if (currentFee !== nextFee) {
    changes.push(`课时费：¥${currentFee}/节 → ¥${nextFee}/节`);
  }

  const currentEffectiveFrom = rule.value.effectiveFrom.slice(0, 10);
  if (currentEffectiveFrom !== effectiveFrom.value) {
    changes.push(`生效日：${currentEffectiveFrom} → ${effectiveFrom.value}`);
  }

  const currentRates = new Map(rule.value.roleRates.map((rate) => [rate.roleId, rate]));
  const nextRatesByRole = new Map(nextRates.map((rate) => [rate.roleId, rate.rateBasisPoints]));
  const roleIds = new Set([...currentRates.keys(), ...nextRatesByRole.keys()]);
  for (const roleId of roleIds) {
    const current = currentRates.get(roleId);
    const role = roles.value.find((item) => item.id === roleId);
    const next = nextRatesByRole.get(roleId) ?? 0;
    const currentBasisPoints = current?.rateBasisPoints ?? 0;
    if (currentBasisPoints === next) continue;
    const roleType = role?.type ?? current?.roleType;
    const roleName = role?.name ?? current?.roleName ?? `角色 #${roleId}`;
    const roleLabel = `${roleType === "delivery" ? "A" : "B"}·${roleName}`;
    changes.push(`${roleLabel}：${percentLabel(currentBasisPoints)} → ${next > 0 ? percentLabel(next) : "移除"}`);
  }

  const retiredNames = retiredRoleRates.value
    .filter((rate) => !nextRatesByRole.has(rate.roleId))
    .map((rate) => rate.roleName);
  return {
    changes,
    warning: retiredNames.length
      ? `将移除已停用角色 ${retiredNames.join("、")} 的未来耗卡提成；历史结算不变`
      : undefined,
  };
}

async function submit() {
  if (!session.currentSiteId || !courseId.value || saving.value || !canWrite.value || loadError.value) return;
  const fee = Number(sessionFee.value);
  if (!Number.isFinite(fee) || fee < 0) {
    uni.showToast({ title: "请输入有效课时费", icon: "none" });
    return;
  }
  const rates: Array<{ roleId: number; rateBasisPoints: number }> = [];
  for (const draft of rateDrafts.value) {
    const percent = Number(draft.percent || 0);
    if (!Number.isFinite(percent) || percent < 0 || percent > 100) {
      uni.showToast({ title: `${draft.role.name} 的比例需在 0% 到 100% 之间`, icon: "none" });
      return;
    }
    const rateBasisPoints = Math.round(percent * 100);
    if (rateBasisPoints > 0) {
      rates.push({ roleId: draft.role.id, rateBasisPoints });
    }
  }
  const nextFee = fee.toFixed(2);
  const summary = buildChangeSummary(nextFee, rates);
  if (!summary.changes.length) {
    uni.showToast({ title: "未检测到需要发布的变更", icon: "none" });
    return;
  }
  const reason = await confirmFinancePublish({
    title: "发布薪酬规则？",
    summaryLines: [`课程：${courseName.value}`, ...summary.changes],
    warning: summary.warning,
    reasonPlaceholder: "例如：九月起调整该课程课时费",
  });
  if (!reason) return;

  saving.value = true;
  try {
    const saved = await updateCourseCompensationRule(session.currentSiteId, courseId.value, {
      deliveryRoleId: deliveryRoleId.value,
      sessionFee: nextFee,
      roleRates: rates,
      effectiveFrom: effectiveFrom.value,
      version: rule.value?.version ?? 0,
      commandKey: createCommandKey(),
      reason,
    });
    rule.value = saved;
    uni.showToast({ title: "规则已保存", icon: "success" });
    setTimeout(() => uni.navigateBack(), 350);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "保存失败", icon: "none" });
  } finally {
    saving.value = false;
  }
}

onLoad((options) => {
  courseId.value = Number(options?.courseId || 0);
  courseName.value = decodeURIComponent(String(options?.name || "课程"));
});

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading && canWrite" class="page-container rule-page">
    <u-alert v-if="loadError" type="error" :description="loadError" />
    <button v-if="loadError" class="retry-btn" @tap="load">重新加载</button>
    <template v-else>
    <view class="course-card">
      <text class="course-label">课程</text>
      <text class="course-name">{{ courseName }}</text>
      <text class="version">{{ rule ? `当前规则版本 v${rule.version}` : "首次配置" }}</text>
    </view>

    <view class="form-card">
      <text class="section-title-text">A 类型课时费</text>
      <text class="section-hint">实际授课员工、A 类型角色和多人分配比例在每个排课场次中配置。</text>
      <view class="field-row">
        <view>
          <text class="field-label">每节课时费</text>
          <text class="field-hint">与耗卡提成独立累计</text>
        </view>
        <view class="money-input"><input v-model="sessionFee" type="digit" /><text>元</text></view>
      </view>
    </view>

    <view class="form-card">
      <text class="section-title-text">A / B 耗卡提成比例</text>
      <text class="section-hint">10 个点按 10% 保存；填写 0 表示该角色不参与本课程耗卡提成。前端只录入比例，不计算最终金额。</text>
      <view v-for="draft in rateDrafts" :key="draft.role.id" class="rate-row">
        <view>
          <text class="rate-name">{{ draft.role.type === "delivery" ? "A" : "B" }} · {{ draft.role.name }}</text>
          <text class="rate-type">{{ draft.role.type === "delivery" ? "实际上课者" : "分成归属" }}</text>
        </view>
        <view class="rate-input"><input v-model="draft.percent" type="digit" /><text>%</text></view>
      </view>
      <u-empty v-if="!rateDrafts.length" mode="list" text="请先创建业务角色" />
      <view v-if="retiredRoleRates.length" class="retired-role-note">
        当前版本仍含已停用角色：{{ retiredRoleRates.map((rate) => rate.roleName).join("、") }}。最终发布确认中会标明移除影响。
      </view>
    </view>

    <view class="form-card">
      <picker mode="date" :value="effectiveFrom" @change="setEffectiveFrom">
        <view class="field-row no-border">
          <view>
            <text class="field-label">生效日期</text>
            <text class="field-hint">历史结算继续使用旧规则快照</text>
          </view>
          <view class="picker-value"><text>{{ effectiveFrom }}</text><u-icon name="arrow-right" size="15" color="#989898" /></view>
        </view>
      </picker>
    </view>

    <button class="save-btn" :disabled="saving" @tap="submit">{{ saving ? "保存中…" : "保存薪酬规则" }}</button>
    </template>
  </view>
  <u-empty v-else-if="!loading" mode="permission" text="暂无课程薪酬编辑权限" />
</template>

<style scoped lang="scss">
.rule-page { padding-bottom: 70rpx; }
.course-card, .form-card { margin-bottom: 20rpx; padding: 28rpx 24rpx; background: #fff; border-radius: $radius-lg; }
.course-label, .course-name, .version { display: block; }
.course-label { color: $color-text-tertiary; font-size: 22rpx; }
.course-name { margin-top: 8rpx; font-size: 34rpx; font-weight: 600; }
.version { margin-top: 8rpx; color: $color-text-tertiary; font-size: 22rpx; }
.section-title-text { display: block; font-size: 29rpx; font-weight: 600; }
.section-hint { display: block; margin-top: 8rpx; color: $color-text-tertiary; font-size: 22rpx; line-height: 34rpx; }
.field-row, .rate-row { display: flex; align-items: center; justify-content: space-between; gap: 20rpx; padding: 25rpx 0; border-bottom: 1rpx solid #f1f1f1; }
.field-row.no-border, .field-row:last-child, .rate-row:last-child { border-bottom: 0; }
.field-label, .field-hint, .rate-name, .rate-type { display: block; }
.field-label, .rate-name { font-size: 27rpx; }
.field-hint, .rate-type { margin-top: 5rpx; color: $color-text-tertiary; font-size: 21rpx; }
.picker-value { display: flex; align-items: center; gap: 8rpx; color: $color-text-secondary; font-size: 25rpx; }
.money-input, .rate-input { display: flex; align-items: center; gap: 8rpx; }
.money-input input, .rate-input input { width: 140rpx; height: 64rpx; padding: 0 14rpx; text-align: right; background: $color-page; border-radius: 10rpx; box-sizing: border-box; }
.money-input text, .rate-input text { color: $color-text-secondary; font-size: 24rpx; }
.save-btn { width: 500rpx; height: 84rpx; margin: 42rpx auto 0; color: $color-text; background: $color-brand-yellow; border-radius: 42rpx; font-size: 30rpx; line-height: 84rpx; }
.save-btn::after { border: 0; }
.retry-btn { width: 360rpx; margin: 32rpx auto; color: $color-text; background: $color-brand-yellow; border-radius: 40rpx; }
.retry-btn::after { border: 0; }
.retired-role-note { margin-top: 20rpx; padding: 18rpx; color: #9a5d00; background: #fff5df; border-radius: 12rpx; font-size: 22rpx; line-height: 1.6; }
</style>
