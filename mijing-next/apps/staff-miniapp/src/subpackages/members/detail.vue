<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPageScroll, onShow } from "@dcloudio/uni-app";
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
import FfBottomLogo from "@/components/ff-bottom-logo/ff-bottom-logo.vue";
import MemberRemarkSheet from "@/components/member-remark-sheet/member-remark-sheet.vue";
import MemberMarkSheet from "@/components/member-mark-sheet/member-mark-sheet.vue";
import MemberTransferSheet from "@/components/member-transfer-sheet/member-transfer-sheet.vue";
import MemberIssueSheet from "@/components/member-issue-sheet/member-issue-sheet.vue";
import MemberClaimSheet from "@/components/member-claim-sheet/member-claim-sheet.vue";
import { updateMemberCardRemark } from "@/api/member-cards";
import { fetchMemberWallet } from "@/api/wallet";
import type {
  CrmMember,
  CrmTag,
  MemberNote,
  MemberStatus,
  StaffBookingHistoryItem,
  StaffMemberCardSummary,
} from "@/types/crm";
import type { MemberMarkFlag } from "@/components/member-mark-sheet/member-mark-sheet.vue";
import type { MemberWallet } from "@/types/wallet";

const session = useSessionStore();

const memberId = ref<number>();
const statusBarHeight = uni.getSystemInfoSync().statusBarHeight ?? 20;
const customBarHeight = (() => {
  try {
    const menu = uni.getMenuButtonBoundingClientRect();
    return menu.height + (menu.top - statusBarHeight) * 2;
  } catch {
    return 44;
  }
})();
const navTotalPx = statusBarHeight + customBarHeight;
const headerBodyPx = uni.upx2px(460);
const headerHeightPx = navTotalPx + headerBodyPx;
const member = ref<CrmMember | null>(null);
const notes = ref<MemberNote[]>([]);
const availableTags = ref<CrmTag[]>([]);
const cards = ref<StaffMemberCardSummary[]>([]);
const wallet = ref<MemberWallet | null>(null);
const archivedCards = ref<StaffMemberCardSummary[]>([]);
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
const showRemarkEditor = ref(false);
const showTransferSheet = ref(false);
const showIssueSheet = ref(false);
const showClaimSheet = ref(false);
const memberMarkFlag = ref<MemberMarkFlag>(0);
const remarkCardTarget = ref<StaffMemberCardSummary | null>(null);
const showCardRemarkEditor = ref(false);
const cardRemarkDraft = ref("");

// 原版 noClassDays 计算：距上次约课天数
const noClassDays = ref<number>(0);

// 原版 dropDown 菜单
const showDropDown = ref(false);

// 原版指标卡数据（getSumCardInfo 对应字段）
interface TotalPayAmount {
  total_pay_amount: string | number | null;
  teamclass_month_count: number | null;
  teamclass_total_count: number | null;
  priclass_month_count: number | null;
  priclass_total_count: number | null;
  absence_count: number | null;
  absent_count_total: number | null;
  expend_price: string | number | null;
  left_price: string | number | null;
  pointStart: number;
  last_month_point: number | null;
  total_point: number | null;
}
const totalPayAmount = ref<TotalPayAmount | null>(null);

// 权限
const canUpdate = computed(() => session.can("crm.member.update"));
const canManageStatus = computed(() => session.can("crm.member.status.manage"));
const canAddNote = computed(() => session.can("crm.member.note.add"));
const canReadNotes = computed(() => session.can("crm.member.note.read"));
const canAssignTags = computed(() => session.can("crm.member.tag.assign"));
const canReadCards = computed(() => session.can("member-card.read") || session.can("crm.member.card.read"));
const canIssueCard = computed(() => session.can("member-card.issue"));
const canReadBookings = computed(() => session.can("booking.member-history.list"));
const canReadArchived = computed(() => session.can("member-card.read") || session.can("crm.member.read"));
const canReadWallet = computed(() => session.can("wallet.read"));
const canAppAccess = computed(() => session.can("crm.member.app_access.manage"));
const currentSiteName = computed(
  () => session.sites.find((site) => site.id === session.currentSiteId)?.name || "本馆",
);

// 原版 hasPermission(58/59)：是否超管
const isAdmin = computed(() => session.can("crm.member.admin"));
const canIssue = computed(() => canIssueCard.value);

// 原版 nameText：优先姓名，其次昵称，其次手机尾号
const nameText = computed(() => {
  if (!member.value) return "—";
  return member.value.name || member.value.mobileMasked?.slice(-4) || "—";
});

// 原版 fixedBarOpacity
const navBarOpacity = ref(0);
onPageScroll((e: { scrollTop: number }) => {
  if (e.scrollTop < 180) {
    navBarOpacity.value = 0;
  } else if (e.scrollTop <= 200) {
    navBarOpacity.value = (e.scrollTop - 180) / 20;
  } else {
    navBarOpacity.value = 1;
  }
});

// 原版 noClassDays → statusImg
const lossStatusImg = computed(() => {
  if (noClassDays.value >= 30 && noClassDays.value < 60) return 1;
  if (noClassDays.value >= 60 && noClassDays.value < 90) return 2;
  if (noClassDays.value >= 90 && noClassDays.value < 120) return 3;
  if (noClassDays.value >= 120) return 4;
  return 0;
});

// 原版信息区字段显示判断
const showUserField = computed(() => (field: number) => {
  // 原版 userField 数组控制字段显示，这里简化：都显示
  return true;
});

// 指标卡点击
function openRechargeAmount() {
  if (!memberId.value) return;
  const name = encodeURIComponent(nameText.value);
  const avatar = encodeURIComponent(member.value?.avatarUrl || "");
  const total = encodeURIComponent(String(totalPayAmount.value?.total_pay_amount ?? "--"));
  uni.navigateTo({
    url: `/subpackages/members/recharge-amount?id=${memberId.value}&name=${name}&avatar=${avatar}&total=${total}`,
  });
}
function openCourseDetail(mode: 0 | 1 | 2) {
  if (!memberId.value) return;
  const t = totalPayAmount.value;
  const name = encodeURIComponent(nameText.value);
  const avatar = encodeURIComponent(member.value?.avatarUrl || "");
  let month = "-";
  let total = "-";
  if (mode === 0) {
    month = String(t?.teamclass_month_count ?? "-");
    total = String(t?.teamclass_total_count ?? "-");
  } else if (mode === 1) {
    month = String(t?.priclass_month_count ?? "--");
    total = String(t?.priclass_total_count ?? "-");
  } else {
    month = String(t?.absence_count ?? "-");
    total = String(t?.absent_count_total ?? "-");
  }
  uni.navigateTo({
    url: `/subpackages/members/course-stats?id=${memberId.value}&mode=${mode}&name=${name}&avatar=${avatar}&month=${encodeURIComponent(month)}&total=${encodeURIComponent(total)}`,
  });
}
function openPoints() {
  if (!memberId.value) return;
  if (!member.value?.pointsEnabled) {
    toast("本场馆未开启积分");
    return;
  }
  const name = encodeURIComponent(nameText.value);
  const avatar = encodeURIComponent(member.value?.avatarUrl || "");
  uni.navigateTo({ url: `/subpackages/members/points?id=${memberId.value}&name=${name}&avatar=${avatar}` });
}

// 标签选择弹窗（对标原版 mark-pop 色旗）
function openMarkPop() {
  if (isAdmin.value) return;
  showTagPicker.value = true;
}
const showTagPicker = ref(false);

const FLAG_COLORS = ["", "#DC3C5C", "#F5A623", "#22C788", "#5FA3EA", "#9B59B6"];

function syncMarkFlagFromMember() {
  const tags = member.value?.tags || [];
  if (!tags.length) {
    memberMarkFlag.value = 0;
    return;
  }
  const color = (tags[0].color || "").toUpperCase();
  const idx = FLAG_COLORS.findIndex((c) => c.toUpperCase() === color);
  memberMarkFlag.value = (idx > 0 ? idx : 0) as MemberMarkFlag;
}

async function onMarkConfirm(flag: MemberMarkFlag) {
  if (!member.value || !session.currentSiteId) return;
  memberMarkFlag.value = flag;
  const color = FLAG_COLORS[flag] || "";
  let tagIds: number[] = [];
  if (flag > 0 && color) {
    const matched = availableTags.value.find((t) => (t.color || "").toUpperCase() === color.toUpperCase());
    if (matched) tagIds = [matched.id];
    else if (availableTags.value[flag - 1]) tagIds = [availableTags.value[flag - 1].id];
  }
  try {
    const response = await syncMemberTags(session.currentSiteId, member.value.id, {
      version: member.value.version,
      tagIds,
    });
    member.value = response.data;
    syncMarkFlagFromMember();
    toastSuccess("编辑成功");
  } catch {
    toast("标签更新失败");
  }
}

// 更多下拉菜单
function toggleDropDown() {
  showDropDown.value = !showDropDown.value;
}
function closeDropDown() {
  showDropDown.value = false;
}

// 编辑资料
function openEdit() {
  if (!memberId.value) return;
  uni.navigateTo({ url: `/subpackages/members/form?id=${memberId.value}` });
}

function goBack() { uni.navigateBack(); }
function toast(msg: string) { uni.showToast({ title: msg, icon: "none" }); }
function toastSuccess(msg: string) { uni.showToast({ title: msg, icon: "success" }); }

// 拨打电话
function callPhone() {
  if (!member.value?.mobileMasked) return;
  const phone = member.value.mobileMasked;
  uni.makePhoneCall({ phoneNumber: phone.replace(/\s/g, "") });
}

// 屏蔽操作
function confirmBlock() {
  if (!member.value || !session.currentSiteId || !reason.value.trim()) {
    toast("请填写操作原因");
    return;
  }
  const status = member.value.appAccessStatus === "allowed" ? "blocked" : "allowed";
  void changeCrmMemberAppAccess(session.currentSiteId, member.value.id, {
    version: member.value.version,
    status,
    reason: reason.value.trim(),
  }).then((response) => {
    member.value = response.data;
    reason.value = "";
    showBlockConfirm.value = false;
    toastSuccess("操作成功");
  }).catch(() => {
    toast("操作失败");
  });
}
const showBlockConfirm = ref(false);
function openBlockConfirm() {
  showDropDown.value = false;
  if (member.value?.appAccessStatus === "blocked") {
    reason.value = "取消屏蔽";
    void confirmBlock();
    return;
  }
  showBlockConfirm.value = true;
}

// 认领
function claimOwner() {
  if (!member.value || !session.currentSiteId) return;
  void claimCrmMemberOwner(session.currentSiteId, member.value.id, {
    version: member.value.version,
  }).then((response) => {
    member.value = response.data;
    toastSuccess("认领成功");
  }).catch(() => {
    toast("认领失败");
  });
}

// 标签
const tagColors = ["#DC3C5C", "#F5A623", "#22C788", "#5FA3EA", "#9B59B6"];

// 当前标签值（tagValue: 0=无, 1~5=颜色）
const currentTagValue = computed(() => {
  if (!member.value?.tags?.length) return 0;
  // 取第一个标签的颜色映射到 tagValue
  const colorMap: Record<string, number> = {
    "#DC3C5C": 1, "#F5A623": 2, "#22C788": 3, "#5FA3EA": 4, "#9B59B6": 5,
  };
  const firstTag = member.value.tags[0];
  return colorMap[firstTag.color] || 0;
});

// 状态标签
function statusLabel(value: MemberStatus) {
  return { lead: "潜客", active: "正式会员", frozen: "已冻结", closed: "已关闭" }[value] || value;
}

function genderText(gender: string | null | undefined) {
  if (gender === "male") return "男";
  if (gender === "female") return "女";
  if (gender === "undisclosed") return "不愿透露";
  return "未设置";
}

// 卡状态
function cardStatusLabel(status: string) {
  return ({
    pending: "待激活", active: "有效", frozen: "冻结",
    expired: "已过期", archived: "已归档", voided: "已作废",
  } as Record<string, string>)[status] || status;
}
function cardTypeLabel(cardType: string) {
  return ({ stored_value: "储值卡", count: "计次卡", period: "期限卡" } as Record<string, string>)[cardType] || "会员卡";
}
function cardTypeRibbonColor(cardType: string) {
  if (cardType === "stored_value") return "rgba(201, 106, 50, 0.88)";
  if (cardType === "count") return "rgba(0, 61, 130, 0.88)";
  return "rgba(52, 159, 145, 0.88)";
}
function cardBalanceText(card: StaffMemberCardSummary) {
  if (card.cardType === "stored_value") {
    const amount = card.cachedBalance != null ? Number(card.cachedBalance) : 0;
    return `${Number.isFinite(amount) ? amount : 0}元`;
  }
  if (card.cardType === "count") {
    return `${card.cachedRemainingCount ?? 0}次`;
  }
  if (card.status === "pending") return card.validUntil || "待开卡";
  return card.validUntil ? `${card.validUntil}` : "—";
}
function cardValidUntilText(card: StaffMemberCardSummary) {
  if (!card.validUntil) return "有效期至 —";
  return card.cardType === "count" ? `有效期${card.validUntil}` : `有效期至${card.validUntil}`;
}
function isCardExhausted(card: StaffMemberCardSummary) {
  if (card.status === "expired" || card.status === "voided" || card.status === "archived") return true;
  if (card.cardType === "stored_value") return Number(card.cachedBalance ?? 0) <= 0;
  if (card.cardType === "count") return Number(card.cachedRemainingCount ?? 0) <= 0;
  return false;
}
function cardNameIsLong(card: StaffMemberCardSummary) {
  return (card.name || "").length > 6;
}

// 预约状态
function bookingStatusLabel(status: string) {
  return ({
    confirmed: "已预约", waitlisted: "候补", cancelled: "已取消",
    absent: "缺席", completed: "已完成",
  } as Record<string, string>)[status] || status;
}
function formatDateTime(value: string | null | undefined) {
  if (!value) return "—";
  return value.slice(0, 16).replace("T", " ");
}

// 指标卡数据（原版横向滚动项；无数据时仍展示 --）
const metricCells = computed(() => {
  const t = totalPayAmount.value;
  const cells: Array<{
    label: string;
    value: string;
    cumulative?: string;
    sub: string;
    action?: () => void;
    showCumulative: boolean;
  }> = [
    { label: "总消费(元)", value: String(t?.total_pay_amount ?? "--"), sub: "累计", action: openRechargeAmount, showCumulative: false },
    { label: "团课", value: String(t?.teamclass_month_count ?? "-"), cumulative: String(t?.teamclass_total_count ?? "-"), sub: "本月/累计", action: () => openCourseDetail(0), showCumulative: true },
    { label: "私教", value: String(t?.priclass_month_count ?? "--"), cumulative: String(t?.priclass_total_count ?? "-"), sub: "本月/累计", action: () => openCourseDetail(1), showCumulative: true },
  ];
  if ((t?.pointStart ?? 0) === 1) {
    cells.push({
      label: "积分",
      value: String(t?.last_month_point ?? "-"),
      cumulative: String(t?.total_point ?? "-"),
      sub: "本月/累计",
      action: openPoints,
      showCumulative: true,
    });
  }
  cells.push(
    { label: "旷课", value: String(t?.absence_count ?? "-"), cumulative: String(t?.absent_count_total ?? "-"), sub: "本月/累计", action: () => openCourseDetail(2), showCumulative: true },
    { label: "已耗卡", value: String(t?.expend_price ?? "--"), sub: "合计", showCumulative: false },
    { label: "剩余价值", value: String(t?.left_price ?? "--"), sub: "合计", showCumulative: false },
  );
  return cells;
});

const tagIconColor = computed(() => {
  const map = ["#fff", "#DC3C5C", "#F5A623", "#22C788", "#5FA3EA", "#9B59B6"];
  return map[currentTagValue.value] || "#fff";
});

// 会员卡列表过滤
const activeCards = computed(() => cards.value.filter((c) => {
  const balance = Number(c.cachedBalance ?? 0);
  const count = Number(c.cachedRemainingCount ?? 0);
  return c.status !== "voided" && c.status !== "archived" && (balance > 0 || count > 0 || c.cardType === "period");
}));
const expiredCards = computed(() => cards.value.filter((c) => !activeCards.value.some((a) => a.id === c.id)));

function cardFaceStyle(card: StaffMemberCardSummary) {
  if (card.faceGradient) return { background: card.faceGradient };
  return { background: "linear-gradient(135deg, #fbd128 0%, #f0a020 100%)" };
}

function applyMemberMetrics(detail: CrmMember) {
  const metrics = detail.metrics;
  noClassDays.value = metrics?.noClassDays ?? 0;
  totalPayAmount.value = {
    total_pay_amount: metrics?.totalPayAmount ?? null,
    teamclass_month_count: metrics?.groupMonthCount ?? null,
    teamclass_total_count: metrics?.groupTotalCount ?? null,
    priclass_month_count: metrics?.privateMonthCount ?? null,
    priclass_total_count: metrics?.privateTotalCount ?? null,
    absence_count: metrics?.absenceMonthCount ?? null,
    absent_count_total: metrics?.absenceTotalCount ?? null,
    expend_price: metrics?.consumedAmount ?? null,
    left_price: metrics?.residualValue ?? null,
    pointStart: detail.pointsEnabled ? 1 : 0,
    last_month_point: detail.totalPoint ?? null,
    total_point: detail.totalPoint ?? null,
  };
}

function openRemarkEditor() {
  stickyRemark.value = member.value?.stickyRemark || "";
  showRemarkEditor.value = true;
}

async function onRemarkConfirm(text: string) {
  stickyRemark.value = text;
  await saveStickyRemark();
  showRemarkEditor.value = false;
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
    syncMarkFlagFromMember();
    applyMemberMetrics(detail.data);
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
  if (canReadCards.value) tasks.push(loadCards());
  tasks.push(loadWallet());
  if (canReadBookings.value) tasks.push(loadBookings());
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

async function loadWallet() {
  if (!memberId.value || !session.currentSiteId || !canReadWallet.value) return;
  try {
    wallet.value = await fetchMemberWallet(session.currentSiteId, memberId.value);
  } catch {
    wallet.value = null;
  }
}

async function loadBookings() {
  if (!memberId.value || !session.currentSiteId || !canReadBookings.value) return;
  bookingsLoading.value = true;
  try {
    const response = await fetchMemberBookingHistory(session.currentSiteId, memberId.value, "past");
    bookings.value = response.data?.items ?? [];
  } catch {
    bookings.value = [];
  } finally {
    bookingsLoading.value = false;
  }
}

// 前台备注
async function saveStickyRemark() {
  if (!member.value || !session.currentSiteId || !canUpdate.value) return;
  stickySaving.value = true;
  try {
    const response = await updateCrmStickyRemark(
      session.currentSiteId, member.value.id,
      member.value.version, stickyRemark.value.trim() || null,
    );
    member.value = response.data;
    stickyRemark.value = response.data.stickyRemark || "";
    toastSuccess("备注已更新");
  } catch (error) {
    toast(error instanceof Error ? error.message : "备注保存失败");
  } finally {
    stickySaving.value = false;
  }
}

// 状态切换
async function transition(target: "active" | "frozen") {
  if (!member.value || !session.currentSiteId || !reason.value.trim()) {
    toast("请填写操作原因");
    return;
  }
  actionLoading.value = true;
  try {
    const response = await transitionCrmMemberStatus(session.currentSiteId, member.value.id, {
      version: member.value.version, targetStatus: target, reason: reason.value.trim(),
    });
    member.value = response.data;
    reason.value = "";
  } catch (error) {
    toast(error instanceof Error ? error.message : "操作失败");
  } finally {
    actionLoading.value = false;
  }
}

// 内部备注
async function addNote() {
  if (!member.value || !session.currentSiteId || !noteBody.value.trim()) return;
  try {
    await addMemberNote(session.currentSiteId, member.value.id, { body: noteBody.value.trim() });
    noteBody.value = "";
    if (canReadNotes.value) {
      const response = await fetchMemberNotes(session.currentSiteId, memberId.value!);
      notes.value = response.data;
    }
    if (member.value.notesCount != null) member.value.notesCount += 1;
    toastSuccess("备注已保存");
  } catch (error) {
    toast(error instanceof Error ? error.message : "备注保存失败");
  }
}

function openTransferSheet() {
  showDropDown.value = false;
  showTransferSheet.value = true;
}

// 发卡
function openIssueCard() {
  if (!memberId.value) return;
  showIssueSheet.value = true;
}

function openClaimNotice() {
  if (!cards.value.length) {
    toast("暂无会员卡可领取");
    return;
  }
  showClaimSheet.value = true;
}

function openCardRemark(card: StaffMemberCardSummary) {
  remarkCardTarget.value = card;
  cardRemarkDraft.value = card.staffRemark || "";
  showCardRemarkEditor.value = true;
}

async function onCardRemarkConfirm(text: string) {
  if (!remarkCardTarget.value || !session.currentSiteId) return;
  try {
    await updateMemberCardRemark(session.currentSiteId, remarkCardTarget.value.id, text.trim() || " ");
    remarkCardTarget.value.staffRemark = text.trim() || null;
    const idx = cards.value.findIndex((c) => c.id === remarkCardTarget.value?.id);
    if (idx >= 0) cards.value[idx] = { ...cards.value[idx], staffRemark: text.trim() || null };
    showCardRemarkEditor.value = false;
    toastSuccess("卡备注已更新");
  } catch (error) {
    toast(error instanceof Error ? error.message : "卡备注保存失败");
  }
}

function onTransferSuccess() {
  void loadMember();
}

function onIssueSuccess() {
  void loadCards();
  void loadWallet();
  void loadMember();
}

function openWallet() {
  if (!memberId.value || !canReadWallet.value) return;
  uni.navigateTo({
    url: `/subpackages/members/wallet?memberId=${memberId.value}&name=${encodeURIComponent(nameText.value)}`,
  });
}

// 卡详情
function openCardDetail(card: StaffMemberCardSummary) {
  if (!memberId.value) return;
  uni.navigateTo({ url: `/subpackages/members/card-detail?memberId=${memberId.value}&memberCardId=${card.id}` });
}

// 归档卡
function openArchivedCards() {
  if (!memberId.value) return;
  const title = encodeURIComponent(nameText.value);
  uni.navigateTo({ url: `/subpackages/members/archived-cards/index?memberId=${memberId.value}&title=${title}` });
}

// 积分
function openPointsPage() {
  if (!memberId.value) return;
  if (!member.value?.pointsEnabled) {
    toast("本场馆未开启积分");
    return;
  }
  uni.navigateTo({ url: `/subpackages/members/points?id=${memberId.value}` });
}

// 领卡提示（原版 notice-card）
const showReceiveNotice = computed(() => {
  return !member.value?.accountLinked && cards.value.length > 0 && !isAdmin.value;
});

onLoad((options) => { memberId.value = Number(options?.id); });
onShow(async () => {
  if (await requireStaffAuth()) await loadMember();
});
</script>

<template>
  <u-loading-page :loading="loading || actionLoading" />
  <view v-if="!loading" class="container" @tap="closeDropDown">
    <view class="personalTainerModule" :style="{ height: `${headerHeightPx}px` }">
      <view class="info-module nav_floating" :style="{ opacity: navBarOpacity, height: `${navTotalPx}px` }">
        <view class="status-bar" :style="{ height: `${statusBarHeight}px` }" />
        <view class="capsule-bar" :style="{ height: `${customBarHeight}px` }">
          <view class="back" :style="{ width: `${customBarHeight}px`, height: `${customBarHeight}px` }" @tap.stop="goBack">
            <u-icon name="arrow-left" size="18" color="#181818" />
          </view>
          <view class="head-img">
            <image v-if="member?.avatarUrl" class="img-head" :src="member.avatarUrl" mode="aspectFill" />
            <view v-else class="img-head img-head-fallback">{{ nameText.slice(0, 1) }}</view>
            <text class="head-realName">{{ nameText }}</text>
          </view>
        </view>
      </view>

      <view class="info-module" :style="{ height: `${headerHeightPx}px` }">
        <view class="status-bar" :style="{ height: `${statusBarHeight}px` }" />
        <view class="capsule-bar" :style="{ height: `${customBarHeight}px` }">
          <view class="back" :style="{ width: `${customBarHeight}px`, height: `${customBarHeight}px` }" @tap.stop="goBack">
            <u-icon name="arrow-left" size="18" color="#fff" />
          </view>
        </view>

        <view
          class="photo-filter"
          :class="{ 'photo-filter-grey': member?.appAccessStatus === 'blocked' }"
          :style="{
            height: `${headerHeightPx}px`,
            backgroundImage: member?.avatarUrl ? `url(${member.avatarUrl})` : 'none',
            backgroundColor: member?.avatarUrl ? 'transparent' : '#696b99',
          }"
        >
          <view class="overlay" />
        </view>

        <view class="wrap">
          <view class="center-wrap">
            <view class="info-wrap">
              <view class="photo-wrap">
                <image
                  v-if="member?.avatarUrl"
                  class="photo-image"
                  :class="{ 'photo-image-grey': member?.appAccessStatus === 'blocked' }"
                  :src="member.avatarUrl"
                  mode="aspectFill"
                />
                <view v-else class="photo-image photo-fallback" :class="{ 'photo-image-grey': member?.appAccessStatus === 'blocked' }">
                  {{ nameText.slice(0, 1) }}
                </view>
                <view v-if="member?.appAccessStatus === 'blocked'" class="forbidden-img">
                  <u-icon name="lock-fill" size="22" color="#fff" />
                </view>
              </view>
              <view class="name">{{ nameText }}</view>
              <view class="handle-wrap" @tap.stop>
                <view class="handle-row">
                  <view v-if="canAssignTags" class="img-wrap" @tap="openMarkPop">
                    <u-icon name="tags-fill" :color="tagIconColor" size="18" />
                  </view>
                  <view v-if="canUpdate" class="img-wrap first" @tap="openEdit">
                    <u-icon name="setting-fill" color="#fff" size="18" />
                  </view>
                </view>
                <view v-if="canAppAccess" class="handle-row handle-row-top">
                  <view class="img-wrap" @tap="toggleDropDown">
                    <u-icon name="more-dot-fill" color="#fff" size="18" />
                  </view>
                </view>
                <view v-if="showDropDown" class="drop_down" @tap.stop>
                  <view class="drop_arrow" />
                  <view class="drop_down_list">
                    <view class="item" @tap="openBlockConfirm">
                      <u-icon :name="member?.appAccessStatus === 'blocked' ? 'eye-fill' : 'eye-off'" size="16" color="#f5f5f5" />
                      <text>{{ member?.appAccessStatus === 'blocked' ? '取消屏蔽' : '屏蔽访问' }}</text>
                    </view>
                    <view class="item" @tap="openTransferSheet">
                      <u-icon name="share-fill" size="16" color="#f5f5f5" />
                      <text>转让账号</text>
                    </view>
                  </view>
                </view>
              </view>
            </view>
          </view>

          <view class="information">
            <view class="information_phone">
              <view v-if="member?.mobileMasked" class="info-row" @tap="callPhone">手机号：{{ member.mobileMasked }}</view>
              <view v-if="member?.name" class="info-row">姓　名：{{ member.name }}</view>
              <view v-if="member?.gender" class="info-row">性　别：{{ genderText(member.gender) }}</view>
              <view v-if="member?.birthDate" class="info-row">生　日：{{ member.birthDate }}</view>
              <view v-if="member?.owner" class="info-row">会　籍：{{ member.owner.name }}</view>
            </view>
            <view class="information_remarks">
              <text>备注：</text>
              <view class="remarks">{{ stickyRemark || "" }}</view>
              <view v-if="canUpdate" class="icon-wrap" @tap="openRemarkEditor">
                <u-icon name="edit-pen" size="14" color="#ffcf00" />
              </view>
            </view>
            <view v-if="lossStatusImg > 0" class="member_status_badge" :class="`status-${lossStatusImg}`">
              {{ lossStatusImg === 1 ? '30天未上课' : lossStatusImg === 2 ? '60天未上课' : lossStatusImg === 3 ? '90天未上课' : '120天未上课' }}
            </view>
          </view>
        </view>
      </view>
    </view>

    <view class="list-wrap">
      <view v-if="errorMessage" class="error-box"><u-alert type="error" :description="errorMessage" /></view>
      <scroll-view class="appointment-scroll" scroll-x :show-scrollbar="false">
        <view class="appointment-info">
          <template v-for="(cell, idx) in metricCells" :key="idx">
            <u-line v-if="idx > 0" direction="col" length="67rpx" color="#DDDDDD" margin="0 20rpx" />
            <view class="appointment_con" @tap="cell.action?.()">
              <view class="appointment_num">
                {{ cell.value }}
                <view v-if="cell.showCumulative" class="cumulative">/{{ cell.cumulative }}</view>
              </view>
              <view class="appointment_text">{{ cell.sub }}</view>
              <view class="appointment_Recharge">{{ cell.label }}</view>
            </view>
          </template>
        </view>
      </scroll-view>

      <view v-if="showReceiveNotice" class="notice-card">
        <view class="card_explain" @tap="openClaimNotice">
          该会员还没领取此卡，通知会员领取
          <u-icon name="arrow-right" size="12" color="#dc3c5c" />
        </view>
      </view>

      <view v-if="wallet" class="wallet-card" @tap="openWallet">
        <view>
          <text class="wallet-label">会员钱包</text>
          <text class="wallet-hint">独立余额 · 可用于余额购卡</text>
        </view>
        <view class="wallet-side">
          <text class="wallet-balance">¥{{ wallet.balance }}</text>
          <u-icon name="arrow-right" size="15" color="#989898" />
        </view>
      </view>

      <view v-if="cards.length > 0" class="new_card">
        <view class="center"><view class="new_card_text cark_title">共{{ cards.length }}张卡</view></view>
        <view v-if="canReadArchived" class="center" @tap="openArchivedCards">
          <view class="new_card_text">删除的卡</view>
          <u-icon name="arrow-right" size="16" color="#989898" />
        </view>
      </view>
      <view v-else class="no_card">
        <view class="no_card_image">
          <view class="empty-card-illus"><u-icon name="coupon" size="72" color="#dadada" /><text>暂无会员卡</text></view>
        </view>
        <view v-if="canReadArchived" class="recovery-del-bt" @tap="openArchivedCards">
          <view class="new_card_text">删除的卡</view>
          <u-icon name="arrow-right" size="16" color="#989898" />
        </view>
      </view>

      <view v-if="cards.length > 0" class="cardList">
        <view class="card_face">
          <view v-for="card in activeCards" :key="card.id" class="card-item" @tap="openCardDetail(card)">
            <view
              class="member-card-face large-size"
              :class="{ grey: isCardExhausted(card) }"
              :style="cardFaceStyle(card)"
            >
              <view class="ribbonUnion" :style="{ background: cardTypeRibbonColor(card.cardType) }">
                <text>{{ cardTypeLabel(card.cardType) }}</text>
              </view>
              <view class="shop-info">
                <view class="shop-avatar">{{ currentSiteName.slice(0, 1) }}</view>
                <text>{{ currentSiteName }}</text>
              </view>
              <view class="card-name" :class="{ 'card-name-size-max': cardNameIsLong(card) }">
                {{ card.name || "会员卡" }}
              </view>
              <view class="bottom-info">
                <view class="balance">{{ cardBalanceText(card) }}</view>
                <view class="period-box">{{ cardValidUntilText(card) }}</view>
              </view>
            </view>
            <view v-if="card.staffRemark" class="remake-box" @tap.stop="openCardRemark(card)">
              <text class="remarkfont">备注：</text>
              <text class="remarkcontent">{{ card.staffRemark }}</text>
            </view>
            <view v-else class="remake-box remake-box--add" @tap.stop="openCardRemark(card)">
              <text class="remarkcontent">添加卡备注</text>
            </view>
          </view>
          <u-divider v-if="expiredCards.length > 0 && activeCards.length > 0" text="已无余额或已过期" textColor="#989898" lineColor="#e5e5e5" :textSize="12" />
          <view v-for="card in expiredCards" :key="`e-${card.id}`" class="card-item" @tap="openCardDetail(card)">
            <view class="member-card-face large-size grey" :style="cardFaceStyle(card)">
              <view class="ribbonUnion" :style="{ background: cardTypeRibbonColor(card.cardType) }">
                <text>{{ cardTypeLabel(card.cardType) }}</text>
              </view>
              <view class="shop-info">
                <view class="shop-avatar">{{ currentSiteName.slice(0, 1) }}</view>
                <text>{{ currentSiteName }}</text>
              </view>
              <view class="card-name" :class="{ 'card-name-size-max': cardNameIsLong(card) }">
                {{ card.name || "会员卡" }}
              </view>
              <view class="bottom-info">
                <view class="balance">{{ cardBalanceText(card) }}</view>
                <view class="period-box">{{ cardValidUntilText(card) }}</view>
              </view>
            </view>
            <view v-if="card.staffRemark" class="remake-box" @tap.stop="openCardRemark(card)">
              <text class="remarkfont">备注：</text>
              <text class="remarkcontent">{{ card.staffRemark }}</text>
            </view>
          </view>
          <view class="click-description">点击会员卡，查看更多信息</view>
        </view>
      </view>
      <FfBottomLogo />
    </view>

    <view v-if="canIssue" class="create-card" @tap.stop="openIssueCard"><view class="bg"><text>发卡</text></view></view>
  </view>

  <u-modal :show="showBlockConfirm" title="确认屏蔽该会员进入？" showCancelButton confirmText="确认" cancelText="取消" @confirm="confirmBlock" @cancel="showBlockConfirm = false" @close="showBlockConfirm = false">
    <view class="block-modal-content">
      <view>1、该会员将<text class="danger">【禁止进入到会员端】</text></view>
      <view>2、该会员将无法看到本馆的排课及所有信息</view>
      <view class="reason-input"><u-input v-model="reason" placeholder="请填写操作原因" border="surround" /></view>
    </view>
  </u-modal>

  <MemberRemarkSheet
    v-model:show="showRemarkEditor"
    :value="stickyRemark"
    @confirm="onRemarkConfirm"
  />
  <MemberMarkSheet
    v-model:show="showTagPicker"
    :value="memberMarkFlag"
    @confirm="onMarkConfirm"
  />
  <MemberTransferSheet
    v-model:show="showTransferSheet"
    :member-id="memberId ?? null"
    :version="member?.version ?? null"
    @success="onTransferSuccess"
  />
  <MemberIssueSheet
    v-model:show="showIssueSheet"
    :member-id="memberId ?? null"
    @success="onIssueSuccess"
  />
  <MemberClaimSheet
    v-model:show="showClaimSheet"
    :member-id="memberId ?? null"
    :member-name="nameText"
    :member-mobile="member?.mobileMasked"
    :cards="cards"
  />
  <MemberRemarkSheet
    v-model:show="showCardRemarkEditor"
    :value="cardRemarkDraft"
    @confirm="onCardRemarkConfirm"
  />
</template>

<style scoped lang="scss">
.container { min-height: 100vh; background: #f5f5f5; }
.personalTainerModule { position: relative; width: 100%; }
.info-module { display: flex; flex-direction: column; left: 0; position: fixed; top: 0; width: 100%; z-index: 99; }
.nav_floating { backdrop-filter: blur(15px); background: hsla(0,0%,100%,0.8); overflow: hidden; z-index: 120; pointer-events: none; }
.nav_floating .back, .nav_floating .head-img { pointer-events: auto; }
.status-bar { width: 100%; }
.capsule-bar { display: flex; align-items: center; width: 100%; }
.back { align-items: center; display: flex; justify-content: center; }
.head-img { align-items: center; display: flex; justify-content: center; flex: 1; margin-right: 80rpx; }
.img-head { border-radius: 50%; height: 40rpx; width: 40rpx; }
.img-head-fallback { align-items: center; background: #fbd128; color: #181818; display: flex; font-size: 22rpx; justify-content: center; }
.head-realName { color: #181818; font-size: 30rpx; margin-left: 8rpx; }
.photo-filter { filter: blur(25rpx); left: 0; position: fixed; top: 0; transform: scale(1.3); width: 100%; background-size: 100% 100%; background-repeat: no-repeat; z-index: -1; }
.photo-filter-grey { filter: blur(25rpx) grayscale(1); }
.overlay { background: #000; height: 100%; opacity: 0.3; width: 100%; }
.wrap { display: flex; flex: 1; flex-direction: column; margin: 0 35rpx; position: relative; z-index: 2; }
.center-wrap { display: flex; justify-content: center; }
.info-wrap { align-items: center; display: flex; flex-direction: column; justify-content: center; position: relative; width: 100%; }
.photo-wrap { border-radius: 50%; height: 139rpx; position: relative; width: 139rpx; overflow: hidden; }
.photo-image { border-radius: 50%; display: block; height: 100%; width: 100%; }
.photo-fallback { align-items: center; background: #fbd128; color: #181818; display: flex; font-size: 48rpx; font-weight: 700; justify-content: center; }
.photo-image-grey { filter: grayscale(1); }
.forbidden-img { left: 92rpx; position: absolute; top: 95rpx; }
.name { color: #fff; font-size: 35rpx; font-weight: 700; line-height: 35rpx; margin-top: 15rpx; text-align: center; }
.handle-wrap { display: flex; flex-direction: column; position: absolute; right: 0; top: 15rpx; }
.handle-row { display: flex; }
.handle-row-top { justify-content: flex-end; margin-top: 20rpx; }
.img-wrap { align-items: center; background: hsla(0,0%,9%,0.3); border-radius: 50%; display: flex; height: 56rpx; justify-content: center; width: 56rpx; }
.img-wrap.first { margin-left: 10rpx; }
.drop_down { position: absolute; right: -11rpx; top: 136rpx; z-index: 10; }
.drop_arrow { margin: 0 23rpx 0 auto; width: 0; height: 0; border-left: 12rpx solid transparent; border-right: 12rpx solid transparent; border-bottom: 14rpx solid hsla(0,0%,9%,0.85); }
.drop_down_list { background: hsla(0,0%,9%,0.85); border-radius: 21rpx; padding: 8rpx 26rpx; }
.drop_down .item { align-items: center; border-bottom: 1px solid #4d4d4d; color: #f5f5f5; display: flex; font-size: 25rpx; gap: 16rpx; padding: 22rpx 0; width: 190rpx; }
.drop_down .item:last-of-type { border-bottom: none; }
.information { color: #fff; font-size: 21rpx; margin-top: 20rpx; position: relative; }
.info-row { line-height: 35rpx; }
.information_phone { display: flex; flex-direction: column; }
.information_remarks { align-items: center; display: flex; line-height: 35rpx; margin-top: 8rpx; }
.remarks { color: #ffcf00; max-width: calc(100% - 125rpx); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.icon-wrap { margin-left: 8rpx; }
.member_status_badge { position: absolute; right: -20rpx; top: -9rpx; padding: 8rpx 18rpx; border-radius: 20rpx 0 0 20rpx; font-size: 20rpx; color: #fff; background: #ee8231; }
.member_status_badge.status-2 { background: #ed920f; }
.member_status_badge.status-3 { background: #dc3c5c; }
.member_status_badge.status-4 { background: #a11; }
.list-wrap { background: #fff; border-top-left-radius: 21rpx; border-top-right-radius: 21rpx; margin-top: -40rpx; min-height: 1100rpx; overflow: hidden; position: relative; z-index: 100; padding-bottom: 200rpx; }
.error-box { padding: 24rpx; }
.appointment-scroll { width: 100%; white-space: nowrap; }
.appointment-info { align-items: center; display: inline-flex; padding: 62rpx 40rpx 50rpx 50rpx; white-space: nowrap; }
.appointment_con { align-items: center; display: inline-flex; flex-direction: column; justify-content: center; min-width: max-content; padding: 0 20rpx; }
.appointment_num { align-items: flex-end; color: #ed920f; display: flex; font-size: 46rpx; font-weight: 500; justify-content: center; min-width: 90rpx; }
.cumulative { color: #ed920f; font-size: 30rpx; font-weight: 400; }
.appointment_text { color: #dadada; font-size: 18rpx; margin-bottom: 8rpx; }
.appointment_Recharge { color: #989898; font-size: 24rpx; }
.notice-card { align-items: center; display: flex; justify-content: center; padding: 0 65rpx; }
.card_explain { background: #ffe9f6; border-radius: 25rpx; color: #dc3c5c; display: flex; align-items: center; gap: 8rpx; font-size: 20rpx; margin-bottom: 40rpx; padding: 13rpx 21rpx; }
.new_card { display: flex; justify-content: space-between; margin: 0 auto; width: 620rpx; }
.center { align-items: center; display: flex; height: 50rpx; }
.new_card_text { color: #7e7e7e; font-size: 24rpx; margin-left: 9rpx; }
.cark_title { color: #181818; font-size: 28rpx; font-weight: 500; }
.no_card { margin: auto; padding-top: 50rpx; width: 408rpx; }
.no_card_image { min-height: 200rpx; }
.empty-card-illus { align-items: center; color: #bfbfbf; display: flex; flex-direction: column; font-size: 24rpx; gap: 16rpx; }
.recovery-del-bt { align-items: center; color: #7e7e7e; display: flex; font-size: 24rpx; height: 50rpx; justify-content: center; padding-top: 30rpx; }
.cardList { padding: 24rpx 65rpx 0; }
.card_face { align-items: center; display: flex; flex-direction: column; width: 100%; }
.card-item { margin-bottom: 40rpx; width: 620rpx; transform: scale(0.98); }
.remake-box {
  display: flex;
  margin-top: 12rpx;
  padding: 12rpx 8rpx;
  color: #989898;
  font-size: 22rpx;
}
.remake-box--add { color: #ed920f; }
.remarkfont { flex-shrink: 0; color: #7e7e7e; }
.remarkcontent { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.member-card-face.large-size {
  border-radius: 18rpx;
  box-shadow: 0 -2rpx 5rpx rgba(0, 0, 0, 0.1);
  color: #fff;
  height: 370rpx;
  overflow: hidden;
  position: relative;
  width: 620rpx;
  box-sizing: border-box;
}
.member-card-face.grey { filter: grayscale(100%); }
.ribbonUnion {
  overflow: hidden;
  position: absolute;
  right: -36rpx;
  top: -3rpx;
  transform: rotate(45deg);
  white-space: nowrap;
}
.ribbonUnion text {
  color: #fff;
  display: block;
  font-weight: 500;
  font-size: 20rpx;
  height: 52rpx;
  line-height: 65rpx;
  padding: 2rpx 29rpx 0;
}
.shop-info {
  align-items: center;
  color: #fff;
  display: flex;
  margin-left: 35rpx;
  margin-top: 30rpx;
}
.shop-avatar {
  width: 70rpx;
  height: 70rpx;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.25);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28rpx;
  font-weight: 600;
}
.shop-info text { font-size: 32rpx; margin-left: 10rpx; }
.card-name {
  align-items: center;
  color: #fff;
  display: flex;
  justify-content: center;
  margin: 0 auto;
  overflow: hidden;
  text-align: center;
  font-size: 80rpx;
  height: 135rpx;
  width: 530rpx;
}
.card-name-size-max { font-size: 65rpx; line-height: 65rpx; }
.bottom-info { color: #fff; position: absolute; z-index: 20; bottom: 25rpx; left: 35rpx; }
.balance { font-size: 35rpx; line-height: 31rpx; }
.period-box { font-size: 24rpx; line-height: 28rpx; margin-top: 4rpx; }
.click-description { color: #989898; font-size: 24rpx; line-height: 28rpx; padding: 20rpx 0 40rpx; text-align: center; }
.create-card { background: rgba(251,209,40,0.2); bottom: 180rpx; height: 139rpx; position: fixed; right: 38rpx; width: 139rpx; z-index: 110; align-items: center; border-radius: 50%; display: flex; justify-content: center; }
.create-card .bg { align-items: center; background: #fbd128; border-radius: 50%; display: flex; height: 125rpx; justify-content: center; width: 125rpx; }
.create-card .bg text { color: #181818; font-size: 32rpx; }
.block-modal-content { font-size: 26rpx; line-height: 1.8; padding: 12rpx 8rpx; }
.danger { color: #dc3c5c; }
.reason-input { margin-top: 16rpx; }
.wallet-card { display: flex; align-items: center; justify-content: space-between; margin: 20rpx 28rpx 0; padding: 24rpx; background: #fff; border-radius: 18rpx; }
.wallet-label, .wallet-hint { display: block; }
.wallet-label { color: #181818; font-size: 28rpx; font-weight: 600; }
.wallet-hint { margin-top: 6rpx; color: #989898; font-size: 21rpx; }
.wallet-side { display: flex; align-items: center; gap: 10rpx; }
.wallet-balance { color: #ed920f; font-size: 31rpx; font-weight: 600; }
</style>
