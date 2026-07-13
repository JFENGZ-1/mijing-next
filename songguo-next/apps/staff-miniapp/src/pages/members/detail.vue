<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { requireStaffAuth } from "@/auth/guard";
import {
  addMemberNote,
  changeCrmMemberAppAccess,
  claimCrmMemberOwner,
  fetchCrmMember,
  fetchMemberBookingHistory,
  fetchMemberCards,
  fetchMemberNotes,
  fetchMemberTags,
  syncMemberTags,
  transitionCrmMemberStatus,
  updateCrmStickyRemark,
} from "@/api/crm";
import { useSessionStore } from "@/stores/session";
import type {
  CrmMember,
  CrmTag,
  MemberNote,
  MemberStatus,
  StaffBookingHistoryItem,
  StaffMemberCardSummary,
} from "@/types/crm";

const session = useSessionStore();
const memberId = ref<number>();
const member = ref<CrmMember | null>(null);
const notes = ref<MemberNote[]>([]);
const availableTags = ref<CrmTag[]>([]);
const cards = ref<StaffMemberCardSummary[]>([]);
const bookings = ref<StaffBookingHistoryItem[]>([]);
const loading = ref(true);
const actionLoading = ref(false);
const stickySaving = ref(false);
const cardsLoading = ref(false);
const bookingsLoading = ref(false);
const errorMessage = ref("");
const reason = ref("");
const noteBody = ref("");
const stickyRemark = ref("");
type DetailTab = "profile" | "cards" | "bookings";
const activeTab = ref<DetailTab>("profile");
const bookingScope = ref<"upcoming" | "past">("upcoming");

const canUpdate = computed(() => session.can("crm.member.update"));
const canManageStatus = computed(() => session.can("crm.member.status.manage"));
const canAddNote = computed(() => session.can("crm.member.note.add"));
const canReadNotes = computed(() => session.can("crm.member.note.read"));
const canAssignTags = computed(() => session.can("crm.member.tag.assign"));
const canReadCards = computed(() => session.can("member-card.read") || session.can("crm.member.card.read"));
const canIssueCard = computed(() => session.can("member-card.issue"));
const canReadBookings = computed(() => session.can("booking.member-history.list"));

const tabs = computed<Array<{ key: DetailTab; label: string }>>(() => {
  const items: Array<{ key: DetailTab; label: string }> = [{ key: "profile", label: "档案" }];
  if (canReadCards.value) items.push({ key: "cards", label: "会员卡" });
  if (canReadBookings.value) items.push({ key: "bookings", label: "预约" });
  return items;
});

function statusLabel(value: MemberStatus) {
  return { lead: "潜客", active: "正式会员", frozen: "已冻结", closed: "已关闭" }[value];
}

function cardStatusLabel(status: string) {
  return ({
    pending: "待激活",
    active: "有效",
    frozen: "冻结",
    expired: "已过期",
    archived: "已归档",
    voided: "已作废",
  } as Record<string, string>)[status] || status;
}

function bookingStatusLabel(status: string) {
  return ({
    confirmed: "已预约",
    waitlisted: "候补",
    cancelled: "已取消",
    absent: "缺席",
    completed: "已完成",
  } as Record<string, string>)[status] || status;
}

function formatDateTime(value: string | null | undefined) {
  if (!value) return "—";
  return value.slice(0, 16).replace("T", " ");
}

function cardBalanceText(card: StaffMemberCardSummary) {
  if (card.cardType === "stored_value") return card.cachedBalance ? `余额 ${card.cachedBalance}` : "余额 —";
  if (card.cardType === "count") return card.cachedRemainingCount != null ? `剩余 ${card.cachedRemainingCount} 次` : "剩余 —";
  return card.validUntil ? `至 ${card.validUntil}` : "";
}

async function loadMember() {
  if (!memberId.value || memberId.value < 1 || !session.currentSiteId) {
    errorMessage.value = "会员参数或场馆上下文无效";
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const detail = await fetchCrmMember(session.currentSiteId, memberId.value);
    member.value = detail.data;
    stickyRemark.value = detail.data.stickyRemark || "";
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "会员档案加载失败";
  } finally {
    loading.value = false;
  }
  if (!member.value) return;

  const tasks: Promise<unknown>[] = [];
  if (canReadNotes.value) {
    tasks.push(fetchMemberNotes(session.currentSiteId, memberId.value)
      .then((response) => { notes.value = response.data; })
      .catch(() => { notes.value = []; }));
  }
  if (canAssignTags.value) {
    tasks.push(fetchMemberTags(session.currentSiteId)
      .then((response) => { availableTags.value = response.data; })
      .catch(() => { availableTags.value = []; }));
  }
  await Promise.all(tasks);
}

async function loadCards() {
  if (!memberId.value || !session.currentSiteId || !canReadCards.value) return;
  cardsLoading.value = true;
  try {
    const response = await fetchMemberCards(session.currentSiteId, memberId.value);
    cards.value = response.data;
  } catch {
    cards.value = [];
  } finally {
    cardsLoading.value = false;
  }
}

async function loadBookings() {
  if (!memberId.value || !session.currentSiteId || !canReadBookings.value) return;
  bookingsLoading.value = true;
  try {
    const response = await fetchMemberBookingHistory(session.currentSiteId, memberId.value, bookingScope.value);
    bookings.value = response.data.items;
  } catch {
    bookings.value = [];
  } finally {
    bookingsLoading.value = false;
  }
}

async function switchTab(tab: DetailTab) {
  activeTab.value = tab;
  if (tab === "cards" && cards.value.length === 0) await loadCards();
  if (tab === "bookings" && bookings.value.length === 0) await loadBookings();
}

async function changeBookingScope(scope: typeof bookingScope.value) {
  bookingScope.value = scope;
  await loadBookings();
}

async function saveStickyRemark() {
  if (!member.value || !session.currentSiteId || !canUpdate.value) return;
  stickySaving.value = true;
  try {
    const response = await updateCrmStickyRemark(
      session.currentSiteId,
      member.value.id,
      member.value.version,
      stickyRemark.value.trim() || null,
    );
    member.value = response.data;
    stickyRemark.value = response.data.stickyRemark || "";
    uni.showToast({ title: "备注已更新", icon: "success" });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "备注保存失败", icon: "none" });
  } finally {
    stickySaving.value = false;
  }
}

async function transition(targetStatus: "active" | "frozen") {
  if (!member.value || !session.currentSiteId || !reason.value.trim()) {
    uni.showToast({ title: "请填写操作原因", icon: "none" });
    return;
  }
  actionLoading.value = true;
  try {
    const response = await transitionCrmMemberStatus(session.currentSiteId, member.value.id, {
      version: member.value.version,
      targetStatus,
      reason: reason.value.trim(),
    });
    member.value = response.data;
    reason.value = "";
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "操作失败", icon: "none" });
  } finally {
    actionLoading.value = false;
  }
}

async function claimOwner() {
  if (!member.value || !session.currentSiteId) return;
  try {
    const response = await claimCrmMemberOwner(session.currentSiteId, member.value.id, {
      version: member.value.version,
    });
    member.value = response.data;
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "认领失败", icon: "none" });
  }
}

async function addNote() {
  if (!member.value || !session.currentSiteId || !noteBody.value.trim()) return;
  try {
    await addMemberNote(session.currentSiteId, member.value.id, { body: noteBody.value.trim() });
    noteBody.value = "";
    if (canReadNotes.value) {
      const response = await fetchMemberNotes(session.currentSiteId, memberId.value!);
      notes.value = response.data;
    }
    uni.showToast({ title: "备注已保存", icon: "success" });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "备注保存失败", icon: "none" });
  }
}

async function chooseTag() {
  if (!member.value || !session.currentSiteId || availableTags.value.length === 0) return;
  uni.showActionSheet({
    itemList: availableTags.value.map((tag) => `${member.value?.tags.some((item) => item.id === tag.id) ? "移除" : "添加"}：${tag.name}`),
    success: async ({ tapIndex }) => {
      const selected = availableTags.value[tapIndex];
      const tagIds = member.value!.tags.some((tag) => tag.id === selected.id)
        ? member.value!.tags.filter((tag) => tag.id !== selected.id).map((tag) => tag.id)
        : [...member.value!.tags.map((tag) => tag.id), selected.id];
      try {
        const response = await syncMemberTags(session.currentSiteId!, member.value!.id, {
          version: member.value!.version,
          tagIds,
        });
        member.value = response.data;
      } catch (error) {
        uni.showToast({ title: error instanceof Error ? error.message : "标签更新失败", icon: "none" });
      }
    },
  });
}

async function toggleAppAccess() {
  if (!member.value || !session.currentSiteId || !reason.value.trim()) {
    uni.showToast({ title: "请填写操作原因", icon: "none" });
    return;
  }
  const status = member.value.appAccessStatus === "allowed" ? "blocked" : "allowed";
  try {
    const response = await changeCrmMemberAppAccess(session.currentSiteId, member.value.id, {
      version: member.value.version,
      status,
      reason: reason.value.trim(),
    });
    member.value = response.data;
    reason.value = "";
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "访问状态更新失败", icon: "none" });
  }
}

function edit() {
  uni.navigateTo({ url: `/pages/members/form?id=${memberId.value}` });
}

function openCardDetail(card: StaffMemberCardSummary) {
  uni.navigateTo({
    url: `/pages/members/card-detail?memberId=${memberId.value}&memberCardId=${card.id}`,
  });
}

function openIssueCard() {
  uni.navigateTo({ url: `/pages/members/issue-card?memberId=${memberId.value}` });
}

onLoad((options) => { memberId.value = Number(options?.id); });
onShow(async () => {
  if (await requireStaffAuth()) {
    await loadMember();
    if (activeTab.value === "cards") await loadCards();
  }
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container detail-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <template v-else-if="member">
      <view class="identity-row">
        <u-avatar :text="member.name?.slice(0, 1) || '?'" size="56" />
        <view class="identity-main">
          <view class="name-row">
            <text class="name">{{ member.name }}</text>
            <u-tag :text="statusLabel(member.status)" size="mini" plain />
          </view>
          <view class="meta">{{ member.mobileMasked || '未留手机号' }} · {{ member.memberNo }}</view>
        </view>
        <button v-if="canUpdate" class="icon-button" title="编辑资料" @click="edit"><u-icon name="edit-pen" size="20" /></button>
      </view>

      <view class="tab-row">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="tab-button"
          :class="{ active: activeTab === tab.key }"
          @click="switchTab(tab.key)"
        >
          {{ tab.label }}
        </button>
      </view>

      <view v-if="activeTab === 'profile'">
        <view class="section-band">
          <view class="section-heading">前台备注</view>
          <view class="section-hint">单行可见备注，覆盖保存；与下方内部备注相互独立</view>
          <u-textarea v-model="stickyRemark" maxlength="500" placeholder="填写会员列表可见备注" :disabled="!canUpdate" />
          <u-button v-if="canUpdate" plain :loading="stickySaving" @click="saveStickyRemark">保存前台备注</u-button>
        </view>

        <view class="section-band">
          <view class="section-heading">CRM 信息</view>
          <u-cell-group>
            <u-cell title="会籍顾问" :value="member.owner?.name || '未分配'" />
            <u-cell title="会员端访问" :value="member.appAccessStatus === 'allowed' ? '允许' : '已限制'" />
            <u-cell title="加入时间" :value="member.joinedAt ? member.joinedAt.slice(0, 10) : '未知'" />
            <u-cell title="登录账户" :value="member.accountLinked ? '已关联' : '未关联潜客'" />
          </u-cell-group>
          <view class="tag-row">
            <u-tag v-for="tag in member.tags" :key="tag.id" :text="tag.name" size="mini" plain />
            <button v-if="canAssignTags" class="link-button" @click="chooseTag">管理标签</button>
          </view>
          <u-button v-if="!member.owner && session.can('crm.member.owner.claim')" plain icon="account" @click="claimOwner">认领为我的会员</u-button>
        </view>

        <view class="section-band">
          <view class="section-heading">内部备注</view>
          <view class="section-hint">仅员工可见，历史备注不可覆盖或删除</view>
          <view v-for="note in notes" :key="note.id" class="note-row">
            <view>{{ note.body }}</view>
            <view class="note-meta">{{ note.author || '未知员工' }} · {{ note.createdAt.slice(0, 16).replace('T', ' ') }}</view>
          </view>
          <template v-if="canAddNote">
            <u-textarea v-model="noteBody" maxlength="2000" placeholder="新增备注" />
            <u-button plain @click="addNote">添加备注</u-button>
          </template>
        </view>

        <view v-if="canManageStatus || session.can('crm.member.app_access.manage')" class="section-band">
          <view class="section-heading">状态操作</view>
          <u-input v-model="reason" placeholder="填写操作原因" />
          <view class="command-grid">
            <u-button v-if="canManageStatus && member.status === 'lead'" type="primary" :loading="actionLoading" @click="transition('active')">转为正式会员</u-button>
            <u-button v-if="canManageStatus && member.status === 'active'" type="warning" :loading="actionLoading" @click="transition('frozen')">冻结会员关系</u-button>
            <u-button v-if="canManageStatus && member.status === 'frozen'" type="primary" :loading="actionLoading" @click="transition('active')">恢复会员关系</u-button>
            <u-button v-if="session.can('crm.member.app_access.manage')" plain @click="toggleAppAccess">
              {{ member.appAccessStatus === 'allowed' ? '限制会员端访问' : '恢复会员端访问' }}
            </u-button>
          </view>
        </view>
      </view>

      <view v-else-if="activeTab === 'cards'" class="section-band">
        <view v-if="canIssueCard" class="cards-toolbar">
          <u-button type="primary" size="small" @click="openIssueCard">发卡</u-button>
        </view>
        <u-loading-page :loading="cardsLoading" />
        <u-empty v-if="!cardsLoading && cards.length === 0" mode="list" text="暂无有效会员卡" />
        <view v-for="card in cards" :key="card.id" class="card-row card-row--tap" @click="openCardDetail(card)">
          <view class="card-title">{{ card.name || card.cardNo }}</view>
          <view class="card-meta">{{ cardStatusLabel(card.status) }} · {{ cardBalanceText(card) }}</view>
          <view class="card-meta">卡号 {{ card.cardNo }}</view>
        </view>
      </view>

      <view v-else class="section-band">
        <view class="scope-row">
          <button class="scope-button" :class="{ active: bookingScope === 'upcoming' }" @click="changeBookingScope('upcoming')">待上课</button>
          <button class="scope-button" :class="{ active: bookingScope === 'past' }" @click="changeBookingScope('past')">历史</button>
        </view>
        <u-loading-page :loading="bookingsLoading" />
        <u-empty v-if="!bookingsLoading && bookings.length === 0" mode="list" text="暂无预约记录" />
        <view v-for="item in bookings" :key="item.id" class="booking-row">
          <view class="card-title">{{ item.courseName || '未命名课程' }}</view>
          <view class="card-meta">{{ bookingStatusLabel(item.status) }} · {{ formatDateTime(item.startsAt) }}</view>
          <view class="card-meta">{{ item.roomName || '未分配教室' }} · {{ item.coachName || '未分配教练' }}</view>
          <view v-if="item.staffNotes" class="card-meta">备注：{{ item.staffNotes }}</view>
        </view>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.detail-page { padding-bottom: 48rpx; }
.identity-row { display: flex; align-items: center; gap: 20rpx; padding: 20rpx 0 28rpx; border-bottom: 1rpx solid $color-border; }
.identity-main { min-width: 0; flex: 1; }
.name-row { display: flex; align-items: center; gap: 12rpx; }
.name { font-size: 36rpx; font-weight: 600; }
.meta, .section-hint, .note-meta, .card-meta { margin-top: 8rpx; color: $color-text-secondary; font-size: 24rpx; }
.icon-button { width: 64rpx; height: 64rpx; padding: 0; line-height: 64rpx; background: transparent; }
.icon-button::after, .link-button::after, .tab-button::after, .scope-button::after { border: 0; }
.tab-row { display: flex; gap: 12rpx; margin: 20rpx 0; }
.tab-button { margin: 0; padding: 14rpx 24rpx; color: $color-text-secondary; font-size: 26rpx; background: $color-page; border: 1rpx solid $color-border; border-radius: 999rpx; }
.tab-button.active { color: #fff; background: $color-primary; border-color: $color-primary; }
.cards-toolbar { display: flex; justify-content: flex-end; margin-bottom: 20rpx; }
.section-band { padding: 28rpx 0; border-bottom: 1rpx solid $color-border; }
.section-heading { font-size: 30rpx; font-weight: 600; }
.tag-row { display: flex; align-items: center; flex-wrap: wrap; gap: 10rpx; margin: 20rpx 0; }
.link-button { margin: 0; padding: 0 12rpx; color: $color-primary; font-size: 24rpx; background: transparent; }
.note-row, .card-row, .booking-row { padding: 20rpx 0; border-bottom: 1rpx solid $color-border; font-size: 27rpx; }
.card-row--tap:active { opacity: 0.7; }
.card-title { font-weight: 600; }
.command-grid { display: grid; gap: 16rpx; margin-top: 20rpx; }
.scope-row { display: flex; gap: 12rpx; margin-bottom: 20rpx; }
.scope-button { margin: 0; padding: 12rpx 24rpx; color: $color-text-secondary; font-size: 24rpx; background: $color-page; border: 1rpx solid $color-border; border-radius: 999rpx; }
.scope-button.active { color: #fff; background: $color-primary; border-color: $color-primary; }
</style>
