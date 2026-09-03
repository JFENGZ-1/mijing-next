<script setup lang="ts">
// 代约选会员弹窗（对标原版 pagesCourse/components/member-search）
// 构成：搜索条（仅本店/全部店）→ 共找到X个会员 → 字母分组列表 + 右侧索引条
// 行为：打开即默认加载（<300 全载，否则前 3 字母）、滚到底/点字母按 3 字母一批懒加载、
// 搜索命中关键词玫红 #DC3C5C 高亮、禁用会员灰显。
import { computed, getCurrentInstance, nextTick, ref, watch } from "vue";
import { fetchBookingPickerMembers } from "@/api/crm";
import type { BookingPickerGroup, BookingPickerMember } from "@/types/crm";
import { useSessionStore } from "@/stores/session";

// createSelectorQuery 的组件实例必须在 setup 同步阶段取好；
// 在 nextTick/异步回调里调 getCurrentInstance() 会拿到 null（微信报 $scope undefined）。
const componentInstance = getCurrentInstance();

const props = defineProps<{ show: boolean; siteId?: number | null }>();
const emit = defineEmits<{
  (e: "update:show", value: boolean): void;
  (e: "select", member: BookingPickerMember): void;
}>();

const session = useSessionStore();

const keyword = ref("");
const loading = ref(false);
const loadingMore = ref(false);
const totalCount = ref(0);
const groups = ref<BookingPickerGroup[]>([]);
const indexList = ref<string[]>([]);
const activeLetter = ref("");
const scrollInto = ref("");
const scopeAll = ref(false);
const storeDropdown = ref(false);
const letterPositions = ref<Array<{ letter: string; top: number }>>([]);

const BATCH_SIZE = 3;

const chainMode = computed(() => session.sites.length > 1);
const searching = computed(() => keyword.value.trim() !== "");
const loadedInitials = computed(() => groups.value.filter((group) => group.items.length > 0).map((group) => group.initial));
const hasData = computed(() => groups.value.some((group) => group.items.length > 0));

let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(
  () => props.show,
  (visible) => {
    if (visible) void open();
  },
);

function reset() {
  keyword.value = "";
  loading.value = false;
  loadingMore.value = false;
  totalCount.value = 0;
  groups.value = [];
  indexList.value = [];
  activeLetter.value = "";
  scrollInto.value = "";
  storeDropdown.value = false;
  letterPositions.value = [];
}

async function open() {
  reset();
  if (!props.siteId) return;
  loading.value = true;
  try {
    const response = await fetchBookingPickerMembers(props.siteId, {
      scope: scopeAll.value ? "all" : "site",
    });
    applyResult(response.data);
    await scrollToFirstLoaded();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "会员加载失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}

function applyResult(data: { totalCount: number; groups: BookingPickerGroup[] }) {
  totalCount.value = data.totalCount;
  groups.value = data.groups;
  indexList.value = data.groups.map((group) => group.initial);
}

/** 懒加载合并：按字母覆盖已有组 */
function mergeGroups(incoming: BookingPickerGroup[]) {
  const map = new Map(groups.value.map((group) => [group.initial, group]));
  for (const group of incoming) {
    if (group.items.length > 0 || map.has(group.initial)) {
      map.set(group.initial, group);
    }
  }
  groups.value = indexList.value.map((initial) => map.get(initial) ?? { initial, count: 0, items: [] });
}

function nextBatchLetters(fromLetter?: string): string[] {
  const loaded = new Set(loadedInitials.value);
  const pending = indexList.value.filter((initial) => !loaded.has(initial));
  if (fromLetter) {
    const startAt = pending.findIndex((initial) => initial === fromLetter);
    if (startAt === -1) {
      // 目标字母已加载，或从目标字母起后续都已加载：取目标字母之后未加载的
      const after = indexList.value.slice(indexList.value.indexOf(fromLetter) + 1).filter((initial) => !loaded.has(initial));
      return after.slice(0, BATCH_SIZE);
    }
    return pending.slice(startAt, startAt + BATCH_SIZE);
  }
  return pending.slice(0, BATCH_SIZE);
}

async function loadBatch(fromLetter?: string): Promise<void> {
  if (!props.siteId || loadingMore.value) return;
  const initials = nextBatchLetters(fromLetter);
  if (initials.length === 0) return;
  loadingMore.value = true;
  try {
    const response = await fetchBookingPickerMembers(props.siteId, {
      scope: scopeAll.value ? "all" : "site",
      initials: initials.join(","),
    });
    mergeGroups(response.data.groups);
    await computeLetterPositions();
  } finally {
    loadingMore.value = false;
  }
}

function onScrollToLower() {
  if (searching.value || loading.value) return;
  if (loadedInitials.value.length >= indexList.value.length) return;
  void loadBatch();
}

function onKeywordInput() {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    void runSearch();
  }, 500);
}

async function runSearch() {
  if (!props.siteId) return;
  loading.value = true;
  try {
    const response = await fetchBookingPickerMembers(props.siteId, {
      q: keyword.value.trim(),
      scope: scopeAll.value ? "all" : "site",
    });
    applyResult(response.data);
    await scrollToFirstLoaded();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "搜索失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}

function toggleStoreDropdown() {
  storeDropdown.value = !storeDropdown.value;
}

function selectScope(all: boolean) {
  storeDropdown.value = false;
  if (scopeAll.value === all) return;
  scopeAll.value = all;
  void open();
}

function anchorId(initial: string): string {
  return initial === "#" ? "anchor-hash" : `anchor-${initial}`;
}

async function tapLetter(letter: string) {
  activeLetter.value = letter;
  if (!loadedInitials.value.includes(letter)) {
    await loadBatch(letter);
  }
  if (!loadedInitials.value.includes(letter)) return; // 该字母无数据
  scrollInto.value = "";
  await nextTick();
  scrollInto.value = anchorId(letter);
}

async function scrollToFirstLoaded() {
  await nextTick();
  const first = groups.value.find((group) => group.items.length > 0)?.initial ?? "";
  activeLetter.value = first;
  scrollInto.value = "";
  await nextTick();
  if (first) scrollInto.value = anchorId(first);
  await computeLetterPositions();
}

/** 索引条高亮相随滚动（简化原版 computeLetterPositions） */
async function computeLetterPositions() {
  await nextTick();
  const query = uni.createSelectorQuery().in(componentInstance?.proxy);
  query.selectAll(".letter-group").boundingClientRect();
  query.select(".member-scroll").boundingClientRect();
  query.exec((nodes) => {
    if (!nodes || nodes.length < 2) return;
    const groupsRect = (nodes[0] ?? []) as Array<{ top: number }>;
    const scrollRect = nodes[1] as { top: number } | null;
    if (!scrollRect) return;
    const letters = groups.value.filter((group) => group.items.length > 0).map((group) => group.initial);
    letterPositions.value = groupsRect.map((rect, index) => ({
      letter: letters[index] ?? "",
      top: rect.top - scrollRect.top,
    })).filter((item) => item.letter);
  });
}

function onScroll(event: { detail?: { scrollTop?: number } }) {
  const scrollTop = event.detail?.scrollTop ?? 0;
  const positions = letterPositions.value;
  if (positions.length === 0) return;
  let current = positions[0]?.letter ?? "";
  for (const position of positions) {
    if (scrollTop + 24 < position.top) break;
    current = position.letter;
  }
  if (current) activeLetter.value = current;
}

function pick(member: BookingPickerMember) {
  emit("select", member);
  close();
}

function close() {
  emit("update:show", false);
}

/** 姓名关键词高亮分段（对标原版 span color:#DC3C5C） */
function nameSegments(name: string | null): Array<{ text: string; hl: boolean }> {
  const value = name ?? "";
  const term = keyword.value.trim();
  if (!term || !value.includes(term)) return [{ text: value, hl: false }];
  const parts: Array<{ text: string; hl: boolean }> = [];
  let rest = value;
  while (rest.includes(term)) {
    const at = rest.indexOf(term);
    if (at > 0) parts.push({ text: rest.slice(0, at), hl: false });
    parts.push({ text: term, hl: true });
    rest = rest.slice(at + term.length);
  }
  if (rest) parts.push({ text: rest, hl: false });
  return parts;
}
</script>

<template>
  <u-popup :show="show" mode="bottom" round="20" @close="close">
    <view class="picker-panel">
      <view class="picker-header">
        <text class="picker-title">选择会员</text>
        <view class="picker-close" @tap="close">
          <u-icon name="close" size="18" color="#989898" />
        </view>
      </view>

      <view class="picker-body">
        <!-- 搜索条（对标原版 .search：灰底胶囊 + 黄搜索图标 + 连锁门店切换） -->
        <view class="search-wrap">
          <view class="search">
            <u-icon class="search-icon" name="search" size="18" color="#ed920f" />
            <view
              v-if="chainMode"
              class="store-switch"
              :class="{ 'is-open': storeDropdown }"
              @tap="toggleStoreDropdown"
            >
              <text>{{ scopeAll ? "全部店" : "仅本店" }}</text>
              <u-icon :name="storeDropdown ? 'arrow-up' : 'arrow-down'" size="12" :color="storeDropdown ? '#fff' : '#7e7e7e'" />
            </view>
            <input
              v-model="keyword"
              class="search-input"
              :class="{ 'with-store': chainMode }"
              placeholder="姓名/手机号"
              placeholder-class="search-placeholder"
              confirm-type="search"
              @input="onKeywordInput"
              @confirm="runSearch"
            />
          </view>
          <view v-if="storeDropdown" class="store-dropdown">
            <view v-if="scopeAll" class="dropdown-item" @tap="selectScope(false)">仅本店</view>
            <view v-else class="dropdown-item" @tap="selectScope(true)">全部店</view>
          </view>
        </view>
        <text class="member-total">共找到 {{ totalCount }} 个会员</text>

        <!-- 列表区 -->
        <view v-if="loading" class="loading-wrap">
          <u-loading-icon size="28" color="#ed920f" />
        </view>
        <template v-else-if="hasData">
          <scroll-view
            class="member-scroll"
            scroll-y
            :scroll-into-view="scrollInto"
            :scroll-with-animation="true"
            @scrolltolower="onScrollToLower"
            @scroll="onScroll"
          >
            <template v-for="group in groups" :key="group.initial">
              <view v-if="group.items.length" :id="anchorId(group.initial)" class="letter-group">
                <view class="letter-header">{{ group.initial }}</view>
                <view
                  v-for="member in group.items"
                  :key="member.id"
                  class="member-item"
                  @tap="pick(member)"
                >
                  <view class="photo-wrap">
                    <image
                      v-if="member.avatarUrl"
                      class="photo"
                      :class="{ grey: member.appAccessStatus === 'blocked' }"
                      :src="member.avatarUrl"
                      mode="aspectFill"
                    />
                    <view v-else class="photo photo-fallback" :class="{ grey: member.appAccessStatus === 'blocked' }">
                      {{ (member.name || "?")[0] }}
                    </view>
                    <view v-if="member.appAccessStatus === 'blocked'" class="forbidden-badge">禁</view>
                  </view>
                  <view class="info-wrap">
                    <view class="name">
                      <text
                        v-for="(segment, segIndex) in nameSegments(member.name)"
                        :key="segIndex"
                        :class="{ hl: segment.hl }"
                      >{{ segment.text }}</text>
                    </view>
                    <view class="card-info">
                      <text v-if="member.joinedAt" class="date">办卡：{{ member.joinedAt }}</text>
                      <text v-if="member.balanceAmount != null" class="balance">
                        余 {{ member.balanceAmount }}{{ member.balanceUnit || "" }}
                      </text>
                      <template v-if="member.otherSiteName">
                        <text class="other-site">门店</text>
                        <text class="other-name">{{ member.otherSiteName }}</text>
                      </template>
                    </view>
                    <view v-if="member.mobileMasked" class="phone">手机号：{{ member.mobileMasked }}</view>
                  </view>
                </view>
              </view>
            </template>
            <view v-if="loadingMore" class="loading-more">加载中…</view>
          </scroll-view>
          <!-- 右侧字母索引条（对标原版 member-index-sidebar） -->
          <view class="index-sidebar">
            <view
              v-for="letter in indexList"
              :key="letter"
              class="index-letter"
              :class="{ active: letter === activeLetter }"
              @tap="tapLetter(letter)"
            >
              {{ letter }}
            </view>
          </view>
        </template>
        <view v-else class="nodata-box">
          <text class="nodata-text">~ 没有找到会员 ~</text>
        </view>
      </view>
    </view>
  </u-popup>
</template>

<style scoped lang="scss">
.picker-panel {
  display: flex;
  flex-direction: column;
  height: 82vh;
  background: #fff;
  border-radius: 20rpx 20rpx 0 0;
  overflow: hidden;
}

.picker-header {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 28rpx 0 12rpx;
}

.picker-title {
  font-size: 32rpx;
  font-weight: 600;
  color: #181818;
}

.picker-close {
  position: absolute;
  right: 28rpx;
  top: 24rpx;
  padding: 10rpx;
}

.picker-body {
  position: relative;
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: 0 33rpx;
}

.search-wrap {
  position: relative;
  padding-top: 12rpx;
}

.search {
  position: relative;
  display: flex;
  align-items: center;
  height: 80rpx;
  padding-left: 73rpx;
  background: #f7f7f7;
  border-radius: 45rpx;
}

.search-icon {
  position: absolute;
  left: 32rpx;
}

.search-input {
  flex: 1;
  height: 100%;
  padding-left: 20rpx;
  font-size: 28rpx;
  color: #2f3133;
}

.search-placeholder {
  color: #b6b6b6;
}

.store-switch {
  display: flex;
  align-items: center;
  gap: 8rpx;
  height: 100%;
  padding: 0 15rpx;
  font-size: 26rpx;
  color: #181818;
  border-radius: 30rpx;

  &.is-open {
    background: rgba(52, 54, 111, 0.82);
    color: #fff;
  }
}

.store-dropdown {
  position: absolute;
  left: 73rpx;
  top: 88rpx;
  z-index: 20;
  min-width: 134rpx;
  padding-left: 15rpx;
  background: rgba(59, 62, 126, 0.92);
  border-radius: 0 0 16rpx 16rpx;
  color: #fff;
  overflow: hidden;
}

.dropdown-item {
  font-size: 24rpx;
  font-weight: 500;
  padding: 22rpx 0;
}

.member-total {
  display: block;
  padding: 24rpx 0 20rpx 10rpx;
  font-size: 22rpx;
  line-height: 28rpx;
  color: #989898;
}

.loading-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 200rpx;
}

.member-scroll {
  flex: 1;
  height: 100%;
}

.letter-group {
  padding: 0 65rpx 0 17rpx;

  & + .letter-group {
    margin-top: 20rpx;
  }
}

.letter-header {
  padding: 8rpx 0 16rpx;
  font-size: 32rpx;
  line-height: 40rpx;
  color: #181818;
}

.member-item {
  position: relative;
  display: flex;
  align-items: flex-start;
  padding: 30rpx 0 8rpx;

  &::after {
    content: "";
    position: absolute;
    right: 12rpx;
    top: 50%;
    width: 14rpx;
    height: 14rpx;
    border-right: 2rpx solid #d8d8d8;
    border-top: 2rpx solid #d8d8d8;
    transform: translateY(-50%) rotate(45deg);
  }
}

.photo-wrap {
  position: relative;
  flex-shrink: 0;
}

.photo {
  width: 84rpx;
  height: 84rpx;
  border-radius: 50%;
  overflow: hidden;
  background: #f0f0f0;

  &.grey {
    filter: grayscale(1);
  }
}

.photo-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 30rpx;
  color: #989898;
}

.forbidden-badge {
  position: absolute;
  right: -4rpx;
  bottom: -4rpx;
  width: 30rpx;
  height: 30rpx;
  border-radius: 50%;
  background: #dc3c5c;
  color: #fff;
  font-size: 18rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.info-wrap {
  flex: 1;
  display: flex;
  flex-direction: column;
  margin-left: 16rpx;
  padding: 0 30rpx 28rpx;
  border-bottom: 1rpx solid #f0f0f0;
}

.name {
  margin-bottom: 12rpx;
  font-size: 26rpx;
  line-height: 28rpx;
  color: #181818;

  .hl {
    color: #dc3c5c;
  }
}

.card-info {
  display: flex;
  align-items: center;
  flex-wrap: nowrap;
  gap: 16rpx;
  font-size: 22rpx;
  line-height: 28rpx;
  color: #989898;
  white-space: nowrap;
}

.other-site {
  margin-left: 10rpx;
  padding: 2rpx 10rpx;
  background: #fcecd7;
  border-radius: 5rpx;
  color: #c96a2f;
  font-size: 20rpx;
}

.other-name {
  color: #181818;
  font-size: 24rpx;
}

.phone {
  margin-top: 6rpx;
  font-size: 24rpx;
  line-height: 32rpx;
  color: #989898;
}

.loading-more {
  padding: 24rpx 0 40rpx;
  text-align: center;
  font-size: 22rpx;
  color: #989898;
}

.index-sidebar {
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
  z-index: 10;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.index-letter {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 56rpx;
  height: 36rpx;
  margin: 3rpx 0;
  font-size: 22rpx;
  font-weight: 500;
  color: #989898;
  border-radius: 20rpx;
  transition: all 0.2s ease;

  &.active {
    background: #515253;
    color: #fff;
    border-radius: 30rpx 0 0 30rpx;
  }
}

.nodata-box {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding-top: 150rpx;
}

.nodata-text {
  color: #bfbfbf;
  font-size: 25rpx;
}
</style>
