<script setup lang="ts">
// 设置关联课程 —— 对标原版（白导航 + 白头卡面区 + 灰底白圆角分区）
// 区块标题行：绿勾全选 + 「团课/私教」 + 右侧「每节扣费(元/次/天)」
// 课程行：绿勾 + 课名 + 标签 + 教练；私教行带教练头徽；期限卡「不扣 ˅」
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { fetchCardProduct, updateCardProduct } from "@/api/card-products";
import { fetchPrivateCoaches, fetchStaffCourseCatalog } from "@/api/catalog";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffCardProductCourseScopeInput, StaffCardProductDetail } from "@/types/member-cards";

const session = useSessionStore();
const checking = ref(true);
const loading = ref(true);
const saving = ref(false);
const cardId = ref(0);
const cardName = ref("");
const detail = ref<StaffCardProductDetail | null>(null);

interface CourseDraft {
  courseId: number;
  name: string;
  courseType: string;
  tag: string;
  coachName: string;
  selected: boolean;
  fee: string; // 储值卡=元 / 次卡=次 / 期限卡=扣除天数
  profileId?: number; // 私教：所属档案
  isUniform?: boolean; // 私教：统一模式（教练行直接带扣费）
}

const drafts = ref<CourseDraft[]>([]);

// 私教档案元信息（分组渲染用，对标原版 single-priv 教练→课目两级）
interface PrivateProfileMeta {
  profileId: number;
  coachName: string;
  tagText: string;
}

const profileMetas = ref<PrivateProfileMeta[]>([]);

const canRead = computed(() => session.can("card-product.catalog.read"));
const canWrite = computed(() => session.can("card-product.editor.write"));

const FACE_FALLBACK = "linear-gradient(135deg, #5f9ea8 0%, #3c7a86 100%)";
const CARD_TYPE_LABELS: Record<string, string> = {
  stored_value: "储值卡",
  count: "次卡",
  period: "期限卡",
};

const cardType = computed(() => detail.value?.cardType || "count");
const isPeriod = computed(() => cardType.value === "period");
const faceGradient = computed(
  () => (detail.value as unknown as { faceGradient?: string | null } | null)?.faceGradient || FACE_FALLBACK,
);
const groupDrafts = computed(() => drafts.value.filter((item) => item.courseType === "group"));
const privateDrafts = computed(() => drafts.value.filter((item) => item.courseType === "private"));
const selectedTeamCount = computed(() => groupDrafts.value.filter((item) => item.selected).length);
const selectedPrivateCount = computed(() => privateDrafts.value.filter((item) => item.selected).length);
const groupAllSelected = computed(
  () => groupDrafts.value.length > 0 && groupDrafts.value.every((item) => item.selected),
);
const privateAllSelected = computed(
  () => privateDrafts.value.length > 0 && privateDrafts.value.every((item) => item.selected),
);

// 私教分组视图（原版 single-priv：教练行 + 缩进课目子行）
interface PrivateGroup {
  meta: PrivateProfileMeta;
  uniform: CourseDraft | null;
  children: CourseDraft[];
}

const privateGroups = computed<PrivateGroup[]>(() =>
  profileMetas.value
    .map((meta) => {
      const mine = privateDrafts.value.filter((draft) => draft.profileId === meta.profileId);
      return {
        meta,
        uniform: mine.find((draft) => draft.isUniform) ?? null,
        children: mine.filter((draft) => !draft.isUniform),
      };
    })
    .filter((group) => group.uniform !== null || group.children.length > 0),
);

function groupChecked(group: PrivateGroup) {
  if (group.uniform) return group.uniform.selected;
  return group.children.length > 0 && group.children.every((draft) => draft.selected);
}

// 教练行勾选：统一模式=勾统一课；课目模式=全选/全不选其课目（对标原版 coachSelect）
function toggleCoach(group: PrivateGroup) {
  if (group.uniform) {
    group.uniform.selected = !group.uniform.selected;
    return;
  }
  const next = !groupChecked(group);
  for (const draft of group.children) draft.selected = next;
}

// 表头「每节扣费(单位)」——期限卡=天、次卡=次、储值卡=元
const feeHeadUnit = computed(() => (isPeriod.value ? "天" : cardType.value === "count" ? "次" : "元"));

async function load() {
  if (!session.currentSiteId || !canRead.value || !cardId.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  try {
    // 团课=课程目录；私教=私教档案（统一模式→教练行，课目模式→各课目行）
    const [cardResponse, catalog, profiles] = await Promise.all([
      fetchCardProduct(session.currentSiteId, cardId.value),
      fetchStaffCourseCatalog(session.currentSiteId, 1, 200, undefined, "group"),
      fetchPrivateCoaches(session.currentSiteId).catch(() => []),
    ]);
    detail.value = cardResponse.data;
    cardName.value = cardResponse.data.name;
    const scopeByCourseId = new Map(
      cardResponse.data.courseScopes
        .filter((scope) => scope.scopeKind === "single")
        .map((scope) => [Number(scope.scopeKey), scope]),
    );
    const feeOf = (courseId: number) => {
      const rawFee = scopeByCourseId.get(courseId)?.priceOverride;
      return rawFee != null && rawFee !== "" ? String(Number(rawFee)) : "";
    };

    const next: CourseDraft[] = catalog.items.map((course) => ({
      courseId: course.id,
      name: course.name,
      courseType: "group",
      tag: course.tags?.[0] ?? "",
      coachName: course.coachName ?? "",
      selected: scopeByCourseId.has(course.id),
      fee: feeOf(course.id),
    }));
    const metas: PrivateProfileMeta[] = [];
    for (const profile of profiles) {
      metas.push({
        profileId: profile.id,
        coachName: profile.coachName || "教练",
        tagText: profile.tagText ?? "",
      });
      if (profile.subjectMode === "per_course") {
        for (const course of profile.courses) {
          next.push({
            courseId: course.id,
            name: course.name,
            courseType: "private",
            tag: profile.tagText ?? "",
            coachName: profile.coachName ?? "",
            selected: scopeByCourseId.has(course.id),
            fee: feeOf(course.id),
            profileId: profile.id,
          });
        }
      } else if (profile.uniformCourseId) {
        next.push({
          courseId: profile.uniformCourseId,
          name: profile.coachName || "私教",
          courseType: "private",
          tag: profile.tagText ?? "",
          coachName: profile.coachName ?? "",
          selected: scopeByCourseId.has(profile.uniformCourseId),
          fee: feeOf(profile.uniformCourseId),
          profileId: profile.id,
          isUniform: true,
        });
      }
    }
    drafts.value = next;
    profileMetas.value = metas;
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "加载失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}

function toggleDraft(draft: CourseDraft) {
  draft.selected = !draft.selected;
}

function toggleAll(courseType: "group" | "private") {
  const list = courseType === "group" ? groupDrafts.value : privateDrafts.value;
  const next = !(courseType === "group" ? groupAllSelected.value : privateAllSelected.value);
  for (const draft of list) draft.selected = next;
}

// —— 期限卡「扣除期限」弹窗（原版 deductionDays） ——
const deductVisible = ref(false);
const deductRule = ref<"no" | "yes">("no");
const deductAmount = ref("");
const deductTarget = ref<CourseDraft | null>(null);

function openDeductPanel(draft: CourseDraft) {
  deductTarget.value = draft;
  const days = Number(draft.fee || 0);
  deductRule.value = days > 0 ? "yes" : "no";
  deductAmount.value = days > 0 ? String(days) : "";
  deductVisible.value = true;
}

function submitDeduct() {
  const draft = deductTarget.value;
  if (!draft) {
    deductVisible.value = false;
    return;
  }
  if (deductRule.value === "yes") {
    const days = Number.parseInt(deductAmount.value.trim() || "0", 10);
    if (!Number.isFinite(days) || days < 1) {
      uni.showToast({ title: "请填写扣除天数", icon: "none" });
      return;
    }
    draft.fee = String(days);
  } else {
    draft.fee = "";
  }
  deductVisible.value = false;
}

function deductLabel(draft: CourseDraft) {
  const days = Number(draft.fee || 0);
  return days > 0 ? `扣除${days}天` : "不扣";
}

// —— 保存（一次全字段回传，防止清空 bookingRules/scopeConfig） ——
async function save() {
  if (!session.currentSiteId || !detail.value) return;
  if (!canWrite.value) {
    uni.showToast({ title: "暂无会员卡设置权限", icon: "none" });
    return;
  }
  for (const draft of drafts.value) {
    if (!draft.selected || isPeriod.value) continue;
    const trimmed = draft.fee.trim();
    if (trimmed && (!Number.isFinite(Number(trimmed)) || Number(trimmed) < 0)) {
      uni.showToast({ title: "扣费数值无效：" + draft.name, icon: "none" });
      return;
    }
  }

  saving.value = true;
  try {
    const scopes: StaffCardProductCourseScopeInput[] = drafts.value
      .filter((draft) => draft.selected)
      .map((draft, index) => {
        const trimmed = draft.fee.trim();
        return {
          scopeKind: "single" as const,
          scopeKey: String(draft.courseId),
          displayName: draft.name,
          priceOverride: trimmed ? Number(trimmed) : undefined,
          sortOrder: index,
        };
      });
    const current = detail.value;
    await updateCardProduct(session.currentSiteId, current.id, {
      version: current.version,
      cardType: current.cardType as "stored_value" | "count" | "period",
      name: current.name,
      price: Number(current.price),
      description: current.description,
      faceValue: current.faceValue != null ? Number(current.faceValue) : undefined,
      initialCount: current.initialCount ?? undefined,
      validityDays: current.validityDays ?? undefined,
      validityMode: current.validityMode,
      activationMode: current.activationMode,
      saleStatus: current.saleStatus as "on_sale" | "stopped",
      sortOrder: current.sortOrder,
      bookingRules: (current.bookingRules as Record<string, unknown> | null) ?? undefined,
      scopeConfig: (current.scopeConfig as Record<string, unknown> | null) ?? undefined,
      courseScopes: scopes,
    });
    uni.showToast({ title: "已保存", icon: "success" });
    setTimeout(() => uni.navigateBack(), 600);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "保存失败", icon: "none" });
  } finally {
    saving.value = false;
  }
}

onLoad((query) => {
  cardId.value = Number(query?.id || 0);
  cardName.value = decodeURIComponent(String(query?.name || ""));
});

onShow(async () => {
  checking.value = true;
  const authenticated = await requireStaffAuth();
  checking.value = false;
  if (!authenticated) return;
  await load();
});
</script>

<template>
  <u-loading-page :loading="checking || loading || saving" />
  <view v-if="!checking" class="page-root">
    <u-empty v-if="!canRead" mode="permission" text="暂无会员卡查看权限" />

    <template v-else-if="detail">
      <!-- 顶部白色区：卡面 + 统计（原版 card-info） -->
      <view class="card-head">
        <view class="head-card" :style="{ background: faceGradient }">
          <text class="head-name">{{ cardName }}</text>
          <text class="head-meta">{{ CARD_TYPE_LABELS[cardType] || cardType }}</text>
          <!-- 右上角 45° 类型缎带 -->
          <view class="ribbon">{{ CARD_TYPE_LABELS[cardType] }}</view>
          <view v-if="detail.saleStatus === 'stopped'" class="stop-mask">
            <text class="stop-text">已停售</text>
          </view>
        </view>
        <text class="head-count">支持{{ selectedTeamCount }}个团课 {{ selectedPrivateCount }}个私教</text>
      </view>

      <!-- 灰底内容区 -->
      <view class="grey-body">
        <!-- 团课区块 -->
        <view v-if="groupDrafts.length" class="section-card">
          <view class="section-head" @tap="toggleAll('group')">
            <view class="row-left">
              <u-icon
                name="checkmark-circle-fill"
                size="24"
                :color="groupAllSelected ? '#22c788' : '#dadada'"
              />
              <text class="section-title">团课</text>
            </view>
            <text class="fee-head">每节扣费({{ feeHeadUnit }})</text>
          </view>
          <view class="section-line" />
          <view v-for="draft in groupDrafts" :key="draft.courseId" class="course-row">
            <view class="row-left" @tap="toggleDraft(draft)">
              <u-icon
                name="checkmark-circle-fill"
                size="24"
                :color="draft.selected ? '#22c788' : '#dadada'"
              />
              <text class="course-name" :class="{ dim: !draft.selected }">{{ draft.name }}</text>
              <text v-if="draft.tag" class="course-tag">{{ draft.tag }}</text>
              <text v-if="draft.coachName" class="coach-suffix">-{{ draft.coachName }}</text>
            </view>
            <view v-if="draft.selected" class="row-right">
              <view v-if="isPeriod" class="deduct-box" @tap="openDeductPanel(draft)">
                <text class="deduct-text">{{ deductLabel(draft) }}</text>
                <u-icon name="arrow-down" size="13" color="#7E7E7E" />
              </view>
              <input
                v-else
                v-model="draft.fee"
                class="fee-input"
                type="digit"
                :placeholder="cardType === 'count' ? '次数' : '金额'"
              />
            </view>
          </view>
        </view>

        <!-- 私教区块（原版 single-priv：教练行 + 缩进课目子行） -->
        <view v-if="privateGroups.length" class="section-card">
          <view class="section-head" @tap="toggleAll('private')">
            <view class="row-left">
              <u-icon
                name="checkmark-circle-fill"
                size="24"
                :color="privateAllSelected ? '#22c788' : '#dadada'"
              />
              <text class="section-title">私教</text>
            </view>
            <text class="fee-head">每节扣费({{ feeHeadUnit }})</text>
          </view>
          <view class="section-line" />

          <view v-for="group in privateGroups" :key="group.meta.profileId" class="priv-group">
            <!-- 教练行：统一模式直接带扣费；课目模式=组头（全选其课目） -->
            <view class="course-row">
              <view class="row-left" @tap="toggleCoach(group)">
                <u-icon
                  name="checkmark-circle-fill"
                  size="24"
                  :color="groupChecked(group) ? '#22c788' : '#dadada'"
                />
                <view class="coach-avatar">{{ group.meta.coachName[0] }}</view>
                <text class="course-name" :class="{ dim: !groupChecked(group) }">{{ group.meta.coachName }}</text>
                <text v-if="group.meta.tagText" class="course-tag">{{ group.meta.tagText }}</text>
              </view>
              <view v-if="group.uniform && group.uniform.selected" class="row-right">
                <view v-if="isPeriod" class="deduct-box" @tap="group.uniform && openDeductPanel(group.uniform)">
                  <text class="deduct-text">{{ deductLabel(group.uniform) }}</text>
                  <u-icon name="arrow-down" size="13" color="#7E7E7E" />
                </view>
                <input
                  v-else
                  v-model="group.uniform.fee"
                  class="fee-input"
                  type="digit"
                  :placeholder="cardType === 'count' ? '次数' : '金额'"
                />
              </view>
            </view>

            <!-- 课目子行（缩进，对标原版 padding-left 66rpx） -->
            <view v-for="draft in group.children" :key="draft.courseId" class="course-row child-row">
              <view class="row-left" @tap="toggleDraft(draft)">
                <u-icon
                  name="checkmark-circle-fill"
                  size="22"
                  :color="draft.selected ? '#22c788' : '#dadada'"
                />
                <text class="course-name child-name" :class="{ dim: !draft.selected }">{{ draft.name }}</text>
              </view>
              <view v-if="draft.selected" class="row-right">
                <view v-if="isPeriod" class="deduct-box" @tap="openDeductPanel(draft)">
                  <text class="deduct-text">{{ deductLabel(draft) }}</text>
                  <u-icon name="arrow-down" size="13" color="#7E7E7E" />
                </view>
                <input
                  v-else
                  v-model="draft.fee"
                  class="fee-input"
                  type="digit"
                  :placeholder="cardType === 'count' ? '次数' : '金额'"
                />
              </view>
            </view>
          </view>
        </view>

        <view v-if="!drafts.length" class="empty-tip">请先在「课程库」添加课目</view>

        <!-- 保存（黄大胶囊，在灰底上） -->
        <view class="btn-box">
          <button class="save-btn" :disabled="saving" @tap="save">保存</button>
        </view>

        <view class="brand-footer">松果约课</view>
      </view>
    </template>

    <!-- 扣除期限弹窗（原版 deductionDays） -->
    <u-popup :show="deductVisible" mode="bottom" round="20" @close="deductVisible = false">
      <view class="panel">
        <text class="panel-title">扣除期限</text>
        <view class="deduct-tips">
          期限卡每过一天系统会自动减少一天，无需任何设置。这里的“不需要扣除”是指不需要额外扣除，比如会员持有一张团课卡，正常场景下她只能约团课；假如也允许她约精品课，那可以通过扣减天数的方式，会员约精品课会直接扣掉N天，用此方式加速耗卡
        </view>
        <view class="deduct-radio" @tap="deductRule = 'no'">
          <u-icon
            :name="deductRule === 'no' ? 'checkmark-circle-fill' : 'checkmark-circle'"
            :color="deductRule === 'no' ? '#22c788' : '#bfbfbf'"
            size="20"
          />
          <text class="deduct-radio-text" :class="{ active: deductRule === 'no' }">不需要扣除</text>
        </view>
        <view class="deduct-radio" @tap="deductRule = 'yes'">
          <u-icon
            :name="deductRule === 'yes' ? 'checkmark-circle-fill' : 'checkmark-circle'"
            :color="deductRule === 'yes' ? '#22c788' : '#bfbfbf'"
            size="20"
          />
          <text class="deduct-radio-text" :class="{ active: deductRule === 'yes' }">扣除</text>
          <input v-model="deductAmount" class="deduct-input" :disabled="deductRule === 'no'" type="number" placeholder="扣除天数" />
          <text class="deduct-radio-text">天</text>
        </view>
        <view v-if="deductRule === 'yes'" class="deduct-warning">
          <text class="warning-bold">警告：</text>“扣除”为高级功能，很少有人使用，若不理解上面的说明则您肯定用不到此功能，请改回【不需要扣除】
        </view>
        <button class="panel-confirm" @tap="submitDeduct">确　定</button>
      </view>
    </u-popup>
  </view>
</template>

<style scoped lang="scss">
.page-root {
  min-height: 100vh;
  background: #f5f5f5;
}

// —— 顶部白色卡面区 ——
.card-head {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 26rpx 0 30rpx;
  background: #fff;
}

.head-card {
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  width: 560rpx;
  height: 330rpx;
  padding: 30rpx 32rpx;
  border-radius: 22rpx;
  box-sizing: border-box;
  overflow: hidden;
}

.head-name {
  overflow: hidden;
  color: #fff;
  font-size: 40rpx;
  font-weight: 600;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.head-meta {
  color: rgba(255, 255, 255, 0.85);
  font-size: 24rpx;
}

// 右上角 45° 缎带
.ribbon {
  position: absolute;
  top: 24rpx;
  right: -66rpx;
  width: 220rpx;
  padding: 8rpx 0;
  background: rgba(255, 255, 255, 0.28);
  color: #fff;
  font-size: 20rpx;
  text-align: center;
  transform: rotate(45deg);
}

.stop-mask {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.45);
}

.stop-text {
  padding: 6rpx 20rpx;
  border: 1rpx solid rgba(255, 255, 255, 0.7);
  border-radius: 999rpx;
  color: #fff;
  font-size: 22rpx;
}

.head-count {
  margin-top: 22rpx;
  color: #505050;
  font-size: 30rpx;
}

// —— 灰底内容 ——
.grey-body {
  padding: 24rpx 24rpx 60rpx;
}

.section-card {
  margin-bottom: 24rpx;
  padding: 8rpx 26rpx;
  background: #fff;
  border-radius: 20rpx;
}

.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 26rpx 0 20rpx;
}

.row-left {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 16rpx;
  min-width: 0;
}

.section-title {
  color: #181818;
  font-size: 32rpx;
  font-weight: 600;
}

.fee-head {
  flex-shrink: 0;
  color: #181818;
  font-size: 28rpx;
  font-weight: 600;
}

.section-line {
  height: 1rpx;
  background: #f0f0f0;
}

.course-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12rpx;
  padding: 26rpx 0;
}

.course-name {
  overflow: hidden;
  color: #181818;
  font-size: 30rpx;
  white-space: nowrap;
  text-overflow: ellipsis;

  &.dim {
    color: #989898;
  }
}

.course-tag {
  flex-shrink: 0;
  padding: 4rpx 12rpx;
  background: #fc8c00;
  border-radius: 8rpx;
  color: #fff;
  font-size: 20rpx;
}

.coach-suffix {
  overflow: hidden;
  color: #bfbfbf;
  font-size: 28rpx;
  white-space: nowrap;
}

.coach-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 88rpx;
  height: 88rpx;
  background: $color-page;
  border-radius: 14rpx;
  color: $color-text-secondary;
  font-size: 30rpx;
}

// 课目子行缩进（原版 padding-left 66rpx）
.child-row {
  padding-left: 66rpx;
}

.child-name {
  font-size: 28rpx;
}

.priv-group {
  & + .priv-group {
    border-top: 1rpx solid #f5f5f5;
  }
}

.row-right {
  flex-shrink: 0;
}

.fee-input {
  width: 150rpx;
  height: 60rpx;
  padding: 0 14rpx;
  background: #f5f5f5;
  border-radius: 10rpx;
  color: #181818;
  font-size: 28rpx;
  text-align: right;
}

.deduct-box {
  display: inline-flex;
  align-items: center;
  gap: 6rpx;
}

.deduct-text {
  color: #181818;
  font-size: 30rpx;
}

.empty-tip {
  padding: 80rpx 0;
  color: #bfbfbf;
  font-size: 26rpx;
  text-align: center;
}

.btn-box {
  display: flex;
  justify-content: center;
  margin-top: 50rpx;
}

.save-btn {
  width: 640rpx;
  height: 96rpx;
  line-height: 96rpx;
  background: $color-brand-yellow;
  border-radius: 48rpx;
  color: #181818;
  font-size: 34rpx;
  font-weight: 500;

  &[disabled] {
    opacity: 0.6;
    color: #181818;
    background: $color-brand-yellow;
  }
}

.save-btn::after {
  border: 0;
}

.brand-footer {
  margin: 110rpx 0 20rpx;
  color: #d8d8d8;
  font-size: 26rpx;
  letter-spacing: 6rpx;
  text-align: center;
}

// —— 扣除期限弹窗 ——
.panel {
  display: flex;
  flex-direction: column;
  gap: 24rpx;
  padding: 40rpx 32rpx calc(40rpx + env(safe-area-inset-bottom));
  background: #fff;
}

.panel-title {
  font-size: 32rpx;
  font-weight: 600;
  text-align: center;
  color: #181818;
}

.deduct-tips {
  padding: 20rpx 22rpx;
  background: #fdf6ec;
  border-radius: 14rpx;
  color: #c96b30;
  font-size: 22rpx;
  line-height: 34rpx;
}

.deduct-radio {
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.deduct-radio-text {
  color: #505050;
  font-size: 28rpx;

  &.active {
    color: #181818;
    font-weight: 500;
  }
}

.deduct-input {
  width: 160rpx;
  height: 64rpx;
  padding: 0 16rpx;
  background: #f5f5f5;
  border-radius: 10rpx;
  color: #181818;
  font-size: 26rpx;
  text-align: center;
}

.deduct-warning {
  color: $color-danger;
  font-size: 24rpx;
  line-height: 36rpx;
}

.warning-bold {
  font-weight: 600;
}

.panel-confirm {
  height: 83rpx;
  margin-top: 6rpx;
  line-height: 83rpx;
  background: $color-brand-yellow;
  border-radius: 42rpx;
  color: #181818;
  font-size: 32rpx;
  font-weight: 500;
}

.panel-confirm::after {
  border: 0;
}
</style>
