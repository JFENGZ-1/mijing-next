<script setup lang="ts">
import { computed, nextTick, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireStaffAuth } from "@/auth/guard";
import { fetchCrmDashboardSummary, fetchCrmMembers } from "@/api/crm";
import MemberCardSheet from "@/components/member-card-sheet/member-card-sheet.vue";
import FfBottomLogo from "@/components/ff-bottom-logo/ff-bottom-logo.vue";
import { useSessionStore } from "@/stores/session";
import type {
  CrmDashboardSummary,
  CrmFilterPresetQuery,
  CrmMember,
  CrmStoredMemberFilters,
  MemberStatus,
} from "@/types/crm";
import { CRM_MEMBER_FILTER_STORAGE_KEY } from "@/types/crm";

const MEMBER_INCLUDE_VISITORS_KEY = "MEMBER_ISUSER";
const BATCH_LETTERS = 3;

const session = useSessionStore();

const loading = ref(true);
const loadingMore = ref(false);
const errorMessage = ref("");
const dashboard = ref<CrmDashboardSummary | null>(null);
const sumMode = ref<string>();
const runOff = ref<number>();
const flag = ref<number>();
const activeFilterLabel = ref("");
const includeVisitors = ref(true);
const showDataHelp = ref(false);

const indexList = ref<string[]>([]);
const groups = ref<Array<{ letter: string; items: CrmMember[] }>>([]);
const listTotal = ref(0);
const scrollInto = ref("");
const activeLetter = ref("");

const cardSheetShow = ref(false);
const cardSheetMemberId = ref<number | null>(null);

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

const canCreate = computed(() => session.can("crm.member.create"));
const canRead = computed(() => session.can("crm.member.read"));
const canReadDeleted = computed(() => session.can("crm.member.deleted.read"));
const canAnalyze = computed(() => session.can("crm.member.read") || session.can("report.read"));
const hasData = computed(() => groups.value.some((group) => group.items.length > 0));
const loadedLetters = computed(() =>
  groups.value.filter((group) => group.items.length > 0).map((group) => group.letter),
);

function statusLabel(value: MemberStatus) {
  return { lead: "潜客", active: "正式", frozen: "冻结", closed: "已关闭" }[value];
}

function balanceText(member: CrmMember) {
  if (member.balanceAmount == null || !member.balanceUnit) return "";
  return `余${member.balanceAmount}${member.balanceUnit}`;
}

function tagColor(member: CrmMember) {
  return member.tags?.[0]?.color || "";
}

function readIncludeVisitors() {
  const raw = uni.getStorageSync(MEMBER_INCLUDE_VISITORS_KEY);
  if (raw === "" || raw === undefined || raw === null) {
    includeVisitors.value = true;
    return;
  }
  includeVisitors.value = raw === true || raw === "true" || raw === 1 || raw === "1";
}

const SUM_MODE_LABELS: Record<string, string> = {
  all: "全部会员",
  monthNew: "本月新增",
  valid: "有效会员",
  invalid: "无效会员",
  noCard: "无卡/访客",
  blocked: "屏蔽会员",
};

function clearFilterState() {
  sumMode.value = undefined;
  runOff.value = undefined;
  flag.value = undefined;
  activeFilterLabel.value = "";
}

function persistFilters(label: string, query: CrmFilterPresetQuery) {
  const payload: CrmStoredMemberFilters = { label, query };
  uni.setStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY, JSON.stringify(payload));
}

function readStoredFilters() {
  const raw = uni.getStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY);
  if (!raw) return;
  try {
    const stored = JSON.parse(raw) as CrmStoredMemberFilters;
    if (stored.cleared) {
      clearFilterState();
      uni.removeStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY);
      return;
    }
    applyFilterQuery(stored.query, stored.label);
  } catch {
    uni.removeStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY);
  }
}

function applyFilterQuery(queryParams: CrmFilterPresetQuery, label?: string) {
  sumMode.value = queryParams.sumMode && queryParams.sumMode !== "all" ? queryParams.sumMode : undefined;
  runOff.value = queryParams.runOff;
  flag.value = queryParams.flag;
  activeFilterLabel.value = label || "";
}

function applySumMode(mode: string) {
  const label = SUM_MODE_LABELS[mode] || mode;
  if (mode === "all") {
    clearFilterState();
    uni.removeStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY);
  } else {
    sumMode.value = mode;
    runOff.value = undefined;
    flag.value = undefined;
    activeFilterLabel.value = label;
    persistFilters(label, { sumMode: mode });
  }
  void reloadList();
}

function clearActiveFilter() {
  clearFilterState();
  uni.setStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY, JSON.stringify({ cleared: true, query: {} }));
  void reloadList();
}

function isSumModeActive(mode: string) {
  if (mode === "all") return !sumMode.value && !runOff.value && !flag.value;
  return sumMode.value === mode && !runOff.value && !flag.value;
}

function anchorId(letter: string) {
  return `letter-${letter === "#" ? "hash" : letter}`;
}

function resetGroups(letters: string[]) {
  indexList.value = letters;
  groups.value = letters.map((letter) => ({ letter, items: [] }));
}

function mergeMembers(items: CrmMember[]) {
  const map = new Map(groups.value.map((group) => [group.letter, group.items.slice()]));
  for (const member of items) {
    const letter = (member.pinyinInitial || "#").toUpperCase();
    if (!map.has(letter)) map.set(letter, []);
    const bucket = map.get(letter)!;
    if (!bucket.some((item) => item.id === member.id)) bucket.push(member);
  }
  const letters = indexList.value.length
    ? indexList.value
    : Array.from(map.keys()).sort((a, b) => a.localeCompare(b));
  if (!indexList.value.length) indexList.value = letters;
  groups.value = letters.map((letter) => ({
    letter,
    items: (map.get(letter) || []).sort((a, b) => b.id - a.id),
  }));
}

async function fetchByInitials(initials: string[]) {
  if (!session.currentSiteId || initials.length === 0) return [] as CrmMember[];
  const response = await fetchCrmMembers(session.currentSiteId, {
    page: 1,
    perPage: 200,
    pinyinInitial: initials.join(","),
    sumMode: sumMode.value,
    runOff: runOff.value,
    flag: flag.value,
    includeVisitors: sumMode.value === "noCard" ? true : includeVisitors.value,
  });
  listTotal.value = response.data.pagination.total;
  return response.data.items;
}

async function fetchAllPages() {
  if (!session.currentSiteId) return [] as CrmMember[];
  const items: CrmMember[] = [];
  let page = 1;
  let lastPage = 1;
  do {
    const response = await fetchCrmMembers(session.currentSiteId, {
      page,
      perPage: 200,
      sumMode: sumMode.value,
      runOff: runOff.value,
      flag: flag.value,
      includeVisitors: sumMode.value === "noCard" ? true : includeVisitors.value,
    });
    items.push(...response.data.items);
    listTotal.value = response.data.pagination.total;
    lastPage = response.data.pagination.lastPage;
    page += 1;
  } while (page <= lastPage && page <= 5);
  return items;
}

async function loadDashboard() {
  if (!session.currentSiteId || !canRead.value) return;
  try {
    const response = await fetchCrmDashboardSummary(session.currentSiteId);
    dashboard.value = response.data;
    const letters = (response.data.pinyinIndex || []).map((item) => item.initial);
    resetGroups(letters);
    if (!sumMode.value && !runOff.value && !flag.value) {
      listTotal.value = response.data.totalCount;
    }
  } catch {
    dashboard.value = null;
    resetGroups([]);
  }
}

async function loadInitialMembers() {
  if (!canRead.value) {
    errorMessage.value = "";
    resetGroups([]);
    listTotal.value = 0;
    return;
  }
  if (!session.currentSiteId) {
    errorMessage.value = "当前账号没有可用场馆";
    return;
  }
  errorMessage.value = "";
  const letters = indexList.value;
  const totalHint = dashboard.value?.totalCount ?? 0;
  try {
    if (sumMode.value || runOff.value || flag.value || totalHint < 300 || letters.length === 0) {
      const items = await fetchAllPages();
      if (letters.length === 0) {
        const derived = Array.from(new Set(items.map((item) => (item.pinyinInitial || "#").toUpperCase()))).sort();
        resetGroups(derived);
      }
      mergeMembers(items);
    } else {
      const batch = letters.slice(0, BATCH_LETTERS);
      const items = await fetchByInitials(batch);
      mergeMembers(items);
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "会员列表加载失败";
  }
}

async function ensureLetterLoaded(letter: string) {
  if (loadedLetters.value.includes(letter) || !session.currentSiteId) return;
  loadingMore.value = true;
  try {
    const items = await fetchByInitials([letter]);
    mergeMembers(items);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "加载失败", icon: "none" });
  } finally {
    loadingMore.value = false;
  }
}

async function loadMoreLetters() {
  if (loadingMore.value || loading.value) return;
  if (sumMode.value || runOff.value || flag.value) return;
  const pending = indexList.value.filter((letter) => !loadedLetters.value.includes(letter));
  if (pending.length === 0) return;
  loadingMore.value = true;
  try {
    const batch = pending.slice(0, BATCH_LETTERS);
    const items = await fetchByInitials(batch);
    mergeMembers(items);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "加载失败", icon: "none" });
  } finally {
    loadingMore.value = false;
  }
}

async function onSelectLetter(letter: string) {
  activeLetter.value = letter;
  await ensureLetterLoaded(letter);
  await nextTick();
  scrollInto.value = "";
  await nextTick();
  scrollInto.value = anchorId(letter);
}

async function reloadList() {
  loading.value = true;
  try {
    await loadDashboard();
    await loadInitialMembers();
  } finally {
    loading.value = false;
    uni.stopPullDownRefresh();
  }
}

async function refresh() {
  readIncludeVisitors();
  readStoredFilters();
  await reloadList();
}

function openSearch() {
  uni.navigateTo({ url: "/pages/members/search" });
}

function openBatch() {
  uni.navigateTo({ url: "/pages/members/batch-ops" });
}

function openFilter() {
  uni.navigateTo({ url: "/pages/members/filter" });
}

function openDeleted() {
  uni.navigateTo({ url: "/pages/members/deleted" });
}

function openMoreAnalysis() {
  if (!canAnalyze.value) {
    uni.showToast({ title: "暂无分析权限", icon: "none" });
    return;
  }
  uni.navigateTo({ url: "/pages/report/member-analyze/index" });
}

function openMember(member: CrmMember) {
  uni.navigateTo({ url: `/pages/members/detail?id=${member.id}` });
}

function openCards(member: CrmMember) {
  cardSheetMemberId.value = member.id;
  cardSheetShow.value = true;
}

function createMember() {
  uni.navigateTo({ url: "/pages/members/form" });
}

function onIncludeVisitorsChange(checked: boolean) {
  includeVisitors.value = checked;
  uni.setStorageSync(MEMBER_INCLUDE_VISITORS_KEY, checked);
  void reloadList();
}

function openDataHelp() {
  showDataHelp.value = true;
}

onShow(async () => {
  if (await requireStaffAuth()) await refresh();
});
onPullDownRefresh(() => refresh());
</script>

<template>
  <view class="member-page">
    <view class="fixed-box">
      <view class="cu-status" :style="{ height: `${statusBarHeight}px` }" />
      <view class="cu-capsule" :style="{ height: `${customBarHeight}px` }">会员管理</view>
      <view class="top-search-box">
        <view class="search-box-flex">
          <view class="search-content">
            <view class="input-box" @tap="openSearch">
              <u-icon name="search" size="36rpx" color="#989898" />
              <text class="tips">会员名/手机号</text>
            </view>
            <view class="headbut">
              <view class="filter" @tap="openBatch">
                <u-icon name="grid-fill" size="44rpx" color="#181818" />
                <text class="te">批量</text>
              </view>
              <view class="filter" @tap="openFilter">
                <u-icon name="list-dot" size="44rpx" color="#181818" />
                <text class="te">筛选</text>
              </view>
            </view>
          </view>
        </view>
      </view>
    </view>

    <view class="placeholder-view" :style="{ height: `${navTotalPx + 55}px` }" />

    <view class="member-data">
      <view class="help_img" @tap="openDataHelp">
        <u-icon name="question-circle" size="31rpx" color="#989898" />
      </view>
      <view class="top-data">
        <view class="item" :class="{ active: isSumModeActive('all') }" @tap="applySumMode('all')">
          <view class="num">{{ dashboard?.totalCount ?? "~" }}</view>
          <text class="lbl">全部会员</text>
        </view>
        <u-line color="#F0F0F0" direction="col" length="63rpx" class="line" />
        <view class="item" :class="{ active: isSumModeActive('monthNew') }" @tap="applySumMode('monthNew')">
          <view class="num">{{ dashboard?.monthCount ?? "~" }}</view>
          <text class="lbl">本月新增</text>
        </view>
      </view>
      <view class="data-box">
        <view class="item" :class="{ active: isSumModeActive('valid') }" @tap="applySumMode('valid')">
          <text class="tex">{{ dashboard?.validUserCount ?? "~" }}</text>
          <view class="vie">有效会员</view>
        </view>
        <u-line color="#F0F0F0" direction="col" length="63rpx" class="u-line" />
        <view class="item" :class="{ active: isSumModeActive('invalid') }" @tap="applySumMode('invalid')">
          <text class="tex">{{ dashboard?.invalidUserCount ?? "~" }}</text>
          <view class="vie">无效会员</view>
        </view>
        <u-line color="#F0F0F0" direction="col" length="63rpx" class="u-line" />
        <view class="item" :class="{ active: isSumModeActive('noCard') }" @tap="applySumMode('noCard')">
          <text class="tex">{{ dashboard?.nocardUserCount ?? "~" }}</text>
          <view class="vie">无卡/访客</view>
        </view>
        <u-line color="#F0F0F0" direction="col" length="63rpx" class="u-line" />
        <view class="item" :class="{ active: isSumModeActive('blocked') }" @tap="applySumMode('blocked')">
          <text class="tex">{{ dashboard?.nologinUserCount ?? "~" }}</text>
          <view class="vie">屏蔽会员</view>
        </view>
        <u-line color="#F0F0F0" direction="col" length="63rpx" class="u-line" />
        <view class="item last-item" @tap="openMoreAnalysis">
          <view class="dots-wrap"><view class="dot" /><view class="dot" /><view class="dot" /></view>
          <view class="vie">更多分析</view>
        </view>
      </view>
    </view>

    <view class="gap-bar" />

    <view class="member-list">
      <view class="filter-row">
        <view class="left-box">
          <u-checkbox
            :checked="includeVisitors"
            label="含无卡/访客"
            labelSize="22rpx"
            labelColor="#989898"
            size="30"
            shape="circle"
            activeColor="#22c788"
            @change="onIncludeVisitorsChange"
          />
        </view>
        <view class="right-box">
          <view v-if="activeFilterLabel" class="filter-chip" @tap="clearActiveFilter">
            <text class="filter-chip-text">{{ activeFilterLabel }}</text>
            <text class="filter-chip-x">×</text>
          </view>
          <text class="total-text">{{ listTotal }} 名</text>
          <view v-if="canReadDeleted" class="deleted-entry" @tap="openDeleted">
            <text>删除记录</text>
            <u-icon name="arrow-right" size="16" color="#989898" />
          </view>
        </view>
      </view>

      <view v-if="loading" class="loading-wrap">
        <u-loading-icon size="28" color="#ed920f" />
      </view>
      <view v-else-if="!canRead" class="noCourseData">
        <view class="memberData">
          <view class="add_btn">仅管理员可见</view>
        </view>
      </view>
      <view v-else-if="errorMessage" class="pad">
        <u-alert type="error" :description="errorMessage" />
      </view>
      <view v-else-if="!hasData" class="noCourseData">
        <view class="memberData">
          <view class="add_btn">
            {{ activeFilterLabel
              ? `「${activeFilterLabel}」暂无匹配会员`
              : (canCreate ? "点击右下方“添加”按钮" : "暂无可查看会员") }}
          </view>
          <view v-if="activeFilterLabel" class="add_btns" @tap="clearActiveFilter">清除筛选</view>
          <view v-else-if="canCreate" class="add_btns">录入场馆的会员</view>
        </view>
      </view>
      <view v-else class="list-body">
        <scroll-view
          class="member-scroll"
          scroll-y
          :scroll-into-view="scrollInto"
          :scroll-with-animation="true"
          @scrolltolower="loadMoreLetters"
        >
          <view v-for="group in groups" :key="group.letter">
            <view v-if="group.items.length" :id="anchorId(group.letter)" class="letter-block">
              <view class="letter-anchor">{{ group.letter }}</view>
              <view
                v-for="member in group.items"
                :key="member.id"
                class="member-item"
              >
                <view class="avatar-wrap" @tap="openMember(member)">
                  <image
                    v-if="member.avatarUrl"
                    class="avator"
                    :class="{ grey: member.appAccessStatus === 'blocked' }"
                    :src="member.avatarUrl"
                    mode="aspectFill"
                  />
                  <view
                    v-else
                    class="avator avator-fallback"
                    :class="{ grey: member.appAccessStatus === 'blocked' }"
                  >
                    {{ (member.name || "?").slice(0, 1) }}
                  </view>
                  <view v-if="member.appAccessStatus === 'blocked'" class="forbidden-badge">
                    <u-icon name="lock-fill" size="12" color="#fff" />
                  </view>
                </view>
                <view class="content">
                  <view class="center" @tap="openMember(member)">
                    <view class="top-info">
                      <text class="name">{{ member.name || "未命名会员" }}</text>
                      <view v-if="member.hasStickyRemark" class="remark-dot" />
                      <view v-if="tagColor(member)" class="flag-dot" :style="{ background: tagColor(member) }" />
                      <text v-if="member.status !== 'active'" class="status-tag" :class="member.status">
                        {{ statusLabel(member.status) }}
                      </text>
                    </view>
                    <view class="bottom-info">
                      <text class="date">{{ member.lastAppointDate || "暂无约课" }}</text>
                      <text v-if="balanceText(member)" class="surplus">{{ balanceText(member) }}</text>
                      <view v-if="member.holidayDate" class="status-chip status-chip--leave">
                        <text class="status-chip-text">请假至 {{ member.holidayDate }}</text>
                      </view>
                      <view v-if="member.hintMsg" class="status-chip status-chip--hint">
                        <text class="status-chip-text">{{ member.hintMsg }}</text>
                      </view>
                    </view>
                  </view>
                  <view class="card-type" @tap.stop="openCards(member)">
                    <u-icon
                      v-if="(member.cardCount || 0) === 0"
                      name="minus-circle"
                      size="42rpx"
                      color="#d8d8d8"
                    />
                    <u-icon
                      v-else-if="(member.cardCount || 0) === 1"
                      name="integral-fill"
                      size="42rpx"
                      color="#ed920f"
                    />
                    <u-icon
                      v-else
                      name="grid-fill"
                      size="42rpx"
                      color="#ed920f"
                    />
                  </view>
                </view>
              </view>
            </view>
          </view>
          <view v-if="loadingMore" class="more-loading">
            <u-loading-icon size="20" color="#ed920f" />
          </view>
          <ff-bottom-logo />
        </scroll-view>

        <view v-if="indexList.length > 1" class="index-bar">
          <text
            v-for="letter in indexList"
            :key="letter"
            class="index-letter"
            :class="{ active: activeLetter === letter }"
            @tap="onSelectLetter(letter)"
          >{{ letter }}</text>
        </view>
      </view>
    </view>

    <button v-if="canCreate && canRead" class="add-button" @click="createMember">
      <u-icon name="plus" color="#fff" size="24" />
    </button>

    <member-card-sheet
      v-model:show="cardSheetShow"
      :member-id="cardSheetMemberId"
    />

    <u-modal
      :show="showDataHelp"
      title="数据说明"
      confirmText="知道了"
      :showCancelButton="false"
      @confirm="showDataHelp = false"
      @close="showDataHelp = false"
    >
      <view class="help-content">
        全部会员：当前场馆会员总数；本月新增：本月新录入发卡会员；有效/无效/无卡/屏蔽为会员状态分类。点「更多分析」可查看流失等扩展指标。
      </view>
    </u-modal>

    <!-- 对标原版 max-mask：无会员读权限时拦截交互 -->
    <view v-if="!canRead" class="max-mask" />
  </view>
</template>

<style scoped lang="scss">
.member-page {
  min-height: 100vh;
  background: #f5f5f5;
}

.placeholder-view {
  width: 100%;
}

.fixed-box {
  background: #fbd128;
  left: 0;
  position: fixed;
  top: 0;
  width: 100%;
  z-index: 20;
}

.cu-status {
  width: 100%;
}

.cu-capsule {
  align-items: center;
  display: flex;
  font-size: 44rpx;
  font-weight: 700;
  text-indent: 26rpx;
  color: #181818;
}

.top-search-box {
  height: 110rpx;
  overflow: hidden;
  width: 100%;
}

.search-box-flex {
  background: #fbd128;
  display: flex;
  flex-direction: column;
  height: 100%;
  padding-left: 35rpx;
  padding-right: 28rpx;
  padding-top: 6rpx;
}

.search-content {
  align-items: center;
  display: flex;
  justify-content: space-between;
}

.input-box {
  align-items: center;
  background: #fff;
  border-radius: 35rpx;
  box-sizing: border-box;
  display: flex;
  flex: 1;
  height: 74rpx;
  margin-right: 26rpx;
  padding-left: 22rpx;
}

.tips {
  color: #989898;
  font-size: 26rpx;
  margin-left: 18rpx;
}

.headbut {
  display: flex;
}

.headbut .filter {
  align-items: center;
  display: flex;
  flex-direction: column;
  text-align: center;
}

.headbut .filter .te {
  color: #181818;
  font-size: 21rpx;
  line-height: 28rpx;
}

.headbut .filter:last-child {
  margin-left: 25rpx;
  padding-right: 10rpx;
}

.member-data {
  align-items: center;
  background: #fff;
  border-top-left-radius: 21rpx;
  border-top-right-radius: 21rpx;
  display: flex;
  flex-direction: column;
  padding: 55rpx 0 40rpx;
  position: relative;
}

.help_img {
  height: 100rpx;
  position: absolute;
  right: 0;
  top: 0;
  width: 100rpx;
  display: flex;
  align-items: flex-start;
  justify-content: flex-end;
  padding: 27rpx 31rpx 0 0;
  box-sizing: border-box;
}

.top-data {
  align-items: center;
  display: flex;
  justify-content: center;
}

.top-data .item {
  align-items: center;
  display: flex;
  flex-direction: column;
  padding: 8rpx 12rpx;
  border-radius: 12rpx;
}

.top-data .item.active .num,
.data-box .item.active .tex {
  color: #ed920f;
}

.top-data .item.active,
.data-box .item.active {
  background: #fff8e6;
}

.top-data .num {
  color: #ed920f;
  font-size: 56rpx;
  font-weight: 700;
}

.top-data .lbl {
  color: #989898;
  font-size: 22rpx;
}

.top-data .line {
  height: 63rpx;
  margin-left: 80rpx;
  margin-right: 80rpx;
}

.data-box {
  align-items: center;
  box-sizing: border-box;
  display: flex;
  justify-content: space-around;
  margin-top: 45rpx;
  padding: 0 35rpx;
  width: 100%;
}

.data-box .item {
  align-items: center;
  display: flex;
  flex-direction: column;
  padding: 8rpx 6rpx;
  border-radius: 12rpx;
}

.data-box .item .tex {
  color: #181818;
  font-size: 33rpx;
}

.data-box .item .vie {
  color: #989898;
  font-size: 22rpx;
  margin-top: 10rpx;
}

.data-box .item.last-item {
  align-self: flex-end;
}

.dots-wrap {
  display: flex;
  gap: 6rpx;
  margin-bottom: 17rpx;
  margin-top: 19rpx;
  height: 8rpx;
  align-items: center;
}

.dots-wrap .dot {
  width: 8rpx;
  height: 8rpx;
  border-radius: 50%;
  background: #c8c9cc;
}

.data-box .u-line {
  flex: 0 !important;
}

.gap-bar {
  height: 24rpx;
  background: #f5f5f5;
}

.member-list {
  background: #fff;
  position: relative;
  min-height: 60vh;
}

.filter-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20rpx 28rpx 8rpx 20rpx;
}

.left-box,
.right-box {
  align-items: center;
  display: flex;
}

.right-box {
  gap: 16rpx;
}

.filter-chip {
  display: flex;
  align-items: center;
  gap: 6rpx;
  max-width: 220rpx;
  padding: 4rpx 12rpx;
  background: #fff8e6;
  border-radius: 999rpx;
  color: #ed920f;
  font-size: 22rpx;
}

.filter-chip-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 160rpx;
}

.filter-chip-x {
  font-size: 24rpx;
  line-height: 1;
}

.deleted-entry {
  display: flex;
  align-items: center;
  gap: 4rpx;
  color: #989898;
  font-size: 22rpx;
}

.total-text {
  color: #989898;
  font-size: 22rpx;
}

.loading-wrap,
.pad {
  padding: 80rpx 32rpx;
  display: flex;
  justify-content: center;
}

.list-body {
  position: relative;
  height: calc(100vh - 520rpx);
  min-height: 700rpx;
}

.member-scroll {
  height: 100%;
  padding-bottom: 120rpx;
  box-sizing: border-box;
}

.letter-anchor {
  padding: 10rpx 58rpx;
  color: #989898;
  font-size: 22rpx;
  background: #fafafa;
}

.member-item {
  align-items: center;
  display: flex;
}

.avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.avator {
  border-radius: 50%;
  display: block;
  height: 84rpx;
  margin-left: 58rpx;
  margin-right: 20rpx;
  width: 84rpx;
}

.avator.grey {
  opacity: 0.45;
  filter: grayscale(1);
}

.avator-fallback {
  align-items: center;
  background: #5fa3ea;
  color: #fff;
  display: flex;
  font-size: 32rpx;
  justify-content: center;
}

.forbidden-badge {
  position: absolute;
  right: 18rpx;
  bottom: 0;
  width: 28rpx;
  height: 28rpx;
  border-radius: 50%;
  background: #dc3c5c;
  display: flex;
  align-items: center;
  justify-content: center;
}

.content {
  align-items: center;
  display: flex;
  flex: 1;
  margin-right: 56rpx;
  padding-bottom: 27rpx;
  padding-top: 30rpx;
  position: relative;
  min-width: 0;
}

.content:after {
  border-bottom: 1px solid #f0f0f0;
  box-sizing: border-box;
  content: " ";
  height: 200%;
  left: -50%;
  pointer-events: none;
  position: absolute;
  top: -50%;
  transform: scale(0.5);
  width: 200%;
  z-index: 1;
}

.center {
  flex: 1;
  min-width: 0;
}

.top-info {
  align-items: center;
  display: flex;
}

.top-info .name {
  color: #181818;
  font-size: 28rpx;
  font-weight: 400;
  margin-right: 9rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 280rpx;
}

.remark-dot {
  width: 18rpx;
  height: 18rpx;
  border-radius: 4rpx;
  background: #fbd128;
  margin-right: 8rpx;
  flex-shrink: 0;
}

.flag-dot {
  width: 16rpx;
  height: 16rpx;
  border-radius: 50%;
  margin-right: 8rpx;
  flex-shrink: 0;
}

.status-tag {
  font-size: 18rpx;
  padding: 2rpx 10rpx;
  border-radius: 19rpx;
  background: #ecf8f3;
  color: #22c788;
  flex-shrink: 0;
}

.status-tag.frozen {
  background: #fef3e0;
  color: #ed920f;
}

.status-tag.closed {
  background: #ffeae1;
  color: #dc3c5c;
}

.status-tag.lead {
  background: #eef4ff;
  color: #5fa3ea;
}

.bottom-info {
  align-items: center;
  display: flex;
  margin-top: 6rpx;
}

.bottom-info .date,
.bottom-info .surplus {
  color: #989898;
  flex-shrink: 0;
  font-size: 21rpx;
}

.bottom-info .date {
  margin-right: 30rpx;
}

.card-type {
  align-items: center;
  border-radius: 50%;
  display: flex;
  height: 72rpx;
  justify-content: center;
  width: 72rpx;
  flex-shrink: 0;
}

.status-chip {
  align-items: center;
  background: #ffeae1;
  border-radius: 19rpx 13rpx 13rpx 0;
  color: #ee8231;
  display: inline-flex;
  font-size: 18rpx;
  line-height: 28rpx;
  margin-left: 14rpx;
  padding: 0 14rpx;
}

.status-chip--hint {
  border-radius: 19rpx 13rpx 13rpx 0;
}

.status-chip-text {
  white-space: nowrap;
}

.index-bar {
  position: absolute;
  right: 4rpx;
  top: 40rpx;
  bottom: 120rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 5;
}

.index-letter {
  font-size: 18rpx;
  color: #989898;
  line-height: 28rpx;
  padding: 0 8rpx;
}

.index-letter.active {
  color: #ed920f;
  font-weight: 600;
}

.more-loading {
  padding: 24rpx;
  display: flex;
  justify-content: center;
}

.noCourseData {
  align-items: center;
  display: flex;
  flex-direction: column;
  height: 800rpx;
}

.noCourseData .memberData {
  align-items: center;
  color: #bfbfbf;
  display: flex;
  flex-direction: column;
  font-size: 25rpx;
  justify-content: center;
  margin-top: 200rpx;
}

.add_btns {
  padding-top: 10rpx;
}

.add-button {
  align-items: center;
  background: #fbd128;
  border-radius: 50%;
  bottom: calc(40rpx + env(safe-area-inset-bottom));
  box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, .16);
  display: flex;
  height: 88rpx;
  justify-content: center;
  padding: 0;
  position: fixed;
  right: 32rpx;
  width: 88rpx;
  z-index: 30;
}

.add-button::after {
  border: 0;
}

.help-content {
  padding: 12rpx 8rpx 24rpx;
  color: #666;
  font-size: 26rpx;
  line-height: 1.6;
}

.max-mask {
  background: transparent;
  height: 100vh;
  left: 0;
  position: fixed;
  top: 0;
  width: 100vw;
  z-index: 9999999;
}
</style>
