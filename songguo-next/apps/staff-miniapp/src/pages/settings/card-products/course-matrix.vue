<script setup lang="ts">
// 设置支持的课及课时费 —— 对标原版 pagesImp/card/card-subject/index
// 每张卡显示可约课统计（N个团课 N个私教 / 可约0个课），点击进入单卡「设置关联课程」
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchCardProducts } from "@/api/card-products";
import { fetchPrivateCoaches, fetchStaffCourseCatalog } from "@/api/catalog";
import type { CoachPrivateProfile } from "@/api/catalog";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffCardProductCatalogItem } from "@/types/member-cards";
import type { CourseCatalogItem } from "@/types/catalog";

const session = useSessionStore();
const checking = ref(true);
const loading = ref(true);
const cards = ref<StaffCardProductCatalogItem[]>([]);
const courses = ref<CourseCatalogItem[]>([]);
const profiles = ref<CoachPrivateProfile[]>([]);

const canRead = computed(() => session.can("card-product.catalog.read"));

const FACE_FALLBACK = "linear-gradient(135deg, #5f9ea8 0%, #3c7a86 100%)";
const CARD_TYPE_LABELS: Record<string, string> = {
  stored_value: "储值卡",
  count: "次卡",
  period: "期限卡",
};

const courseTypeById = computed(() => {
  const map = new Map<number, string>();
  for (const course of courses.value) map.set(course.id, course.courseType);
  // 私教档案（统一模式隐藏课 + 课目）均计为私教
  for (const profile of profiles.value) {
    if (profile.uniformCourseId) map.set(profile.uniformCourseId, "private");
    for (const course of profile.courses) map.set(course.id, "private");
  }
  return map;
});

interface CardStat {
  card: StaffCardProductCatalogItem;
  teamCount: number;
  privateCount: number;
  total: number;
}

const cardStats = computed<CardStat[]>(() =>
  cards.value.map((card) => {
    let teamCount = 0;
    let privateCount = 0;
    for (const key of card.courseScopeKeys ?? []) {
      const type = courseTypeById.value.get(key);
      if (type === "private") privateCount++;
      else if (type === "group") teamCount++;
    }
    return { card, teamCount, privateCount, total: teamCount + privateCount };
  }),
);

// 有 N 张新卡需要设置（在售且未关联任何课）
const noConfigCardCount = computed(
  () => cardStats.value.filter((item) => item.total === 0 && item.card.saleStatus === "on_sale").length,
);
// 有 N 个新课没有任何卡支持（含私教档案的课目/统一课）
const noConfigCourseCount = computed(() => {
  const covered = new Set<number>();
  for (const card of cards.value) {
    for (const key of card.courseScopeKeys ?? []) covered.add(key);
  }
  let count = 0;
  for (const id of courseTypeById.value.keys()) {
    if (!covered.has(id)) count++;
  }
  return count;
});

async function load() {
  if (!session.currentSiteId || !canRead.value) {
    loading.value = false;
    return;
  }
  try {
    const [cardResponse, catalog, profileItems] = await Promise.all([
      fetchCardProducts(session.currentSiteId, 1, 50, undefined, "active"),
      session.can("course-catalog.read")
        ? fetchStaffCourseCatalog(session.currentSiteId, 1, 200, undefined, "group")
        : Promise.resolve(null),
      session.can("course-catalog.read")
        ? fetchPrivateCoaches(session.currentSiteId).catch(() => [])
        : Promise.resolve([]),
    ]);
    cards.value = cardResponse.data.items;
    if (catalog) courses.value = catalog.items;
    profiles.value = profileItems;
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "加载失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}

function openSetting(item: CardStat) {
  uni.navigateTo({
    url: `/pages/settings/card-products/course-setting?id=${item.card.id}&name=${encodeURIComponent(item.card.name)}`,
  });
}

onShow(async () => {
  checking.value = true;
  const authenticated = await requireStaffAuth();
  checking.value = false;
  if (!authenticated) return;
  await load();
});

onPullDownRefresh(async () => {
  await load();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="checking || loading" />
  <view v-if="!checking" class="page-shell">
    <view class="body-sheet">
      <u-empty v-if="!canRead" mode="permission" text="暂无会员卡查看权限" />

      <template v-else-if="cards.length">
        <!-- 顶部提示（原版 hint） -->
        <view class="hint">
          <u-icon name="bell" size="18" color="#C96A2F" />
          <text class="hint-text">不同的卡约不同的课，在此设置每张卡可以预约的课程及课时费</text>
        </view>

        <!-- 待设置警示（原版 noRelevancy-num） -->
        <view v-if="noConfigCardCount > 0" class="warn-row">有 {{ noConfigCardCount }} 张新卡需要设置</view>
        <view v-else-if="noConfigCourseCount > 0" class="warn-row">
          有 {{ noConfigCourseCount }} 个新课，目前还没有任何卡支持它，请检查
        </view>

        <view class="card-total">共{{ cards.length }}种会员卡</view>

        <!-- 卡列表：迷你卡面 + 可约统计 -->
        <view v-for="item in cardStats" :key="item.card.id" class="card-row" @tap="openSetting(item)">
          <view class="mini-card" :style="{ background: item.card.faceGradient || FACE_FALLBACK }">
            <text class="mini-name">{{ item.card.name }}</text>
            <text class="mini-type">{{ CARD_TYPE_LABELS[item.card.cardType] || item.card.cardType }}</text>
            <view v-if="item.card.saleStatus === 'stopped'" class="stop-mask">
              <text class="stop-text">已停售</text>
            </view>
          </view>
          <view class="set-meal">
            <view class="set-meal-text">
              <text v-if="item.teamCount > 0" class="count-text">{{ item.teamCount }}个团课</text>
              <text v-if="item.privateCount > 0" class="count-text">{{ item.privateCount }}个私教</text>
              <text v-if="item.total === 0" class="not-project">可约0个课</text>
            </view>
            <u-icon name="arrow-right" size="16" color="#989898" />
          </view>
        </view>
      </template>

      <!-- 空态（原版 noData） -->
      <view v-else-if="!loading" class="no-data">
        <u-icon name="order" size="64" color="#dadada" />
        <view class="no-data-text">
          <view>请先添加“课目”与“会员卡”后</view>
          <view>再在此设置会员卡所支持的课程及课时费用</view>
        </view>
      </view>

      <view class="brand-footer">松果约课</view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.page-shell {
  min-height: 100vh;
  background: $color-brand-yellow;
}

.body-sheet {
  min-height: 100vh;
  padding: 30rpx 28rpx 60rpx;
  background: #fff;
  border-radius: 20rpx 20rpx 0 0;
  box-sizing: border-box;
}

.hint {
  display: flex;
  align-items: flex-start;
  gap: 10rpx;
  padding: 20rpx 22rpx;
  background: #fdf6ec;
  border-radius: 14rpx;
}

.hint-text {
  flex: 1;
  color: #c96a2f;
  font-size: 21rpx;
  line-height: 32rpx;
}

.warn-row {
  margin-top: 20rpx;
  color: $color-danger;
  font-size: 26rpx;
  font-weight: 500;
}

.card-total {
  margin: 24rpx 0 6rpx;
  color: #181818;
  font-size: 28rpx;
}

.card-row {
  display: flex;
  align-items: center;
  gap: 22rpx;
  padding: 28rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
}

.mini-card {
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  flex-shrink: 0;
  width: 300rpx;
  height: 178rpx;
  padding: 20rpx 22rpx;
  border-radius: 16rpx;
  box-sizing: border-box;
  overflow: hidden;
}

.mini-name {
  overflow: hidden;
  color: #fff;
  font-size: 30rpx;
  font-weight: 600;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.mini-type {
  color: rgba(255, 255, 255, 0.8);
  font-size: 22rpx;
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

.set-meal {
  display: flex;
  flex: 1;
  align-items: center;
  justify-content: flex-end;
  gap: 8rpx;
  min-width: 0;
}

.set-meal-text {
  text-align: right;
}

.count-text {
  margin-left: 6rpx;
  color: $color-text;
  font-size: 26rpx;
}

.not-project {
  color: $color-danger;
  font-size: 26rpx;
  font-weight: 500;
}

.no-data {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 26rpx;
  padding: 140rpx 0 40rpx;
}

.no-data-text {
  color: $color-text-tertiary;
  font-size: 26rpx;
  line-height: 44rpx;
  text-align: center;
}

.brand-footer {
  margin: 90rpx 0 20rpx;
  color: #d8d8d8;
  font-size: 26rpx;
  letter-spacing: 6rpx;
  text-align: center;
}
</style>
