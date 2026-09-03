<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import {
  bookMemberPrivateCoach,
  getMemberPrivateCoachPayableCards,
  getMemberPrivateCoachProfile,
  getMemberPrivateCoachTimeSlots,
} from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import { ensureMemberContext } from "@/composables/member-context";
import type {
  MemberCardWalletSummary,
  MemberPrivateCoachProfile,
  MemberPrivateCoachTimeSlot,
} from "@/types/member";
import { createCommandKey } from "@/utils/command-key";
import { formatApiErrorMessage } from "@/utils/api-error";

function localDateKey(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

function shiftIso(iso: string, days: number) {
  const d = new Date(`${iso}T00:00:00`);
  d.setDate(d.getDate() + days);
  return localDateKey(d);
}

const weekdayCn = ["日", "一", "二", "三", "四", "五", "六"];
const todayIso = localDateKey(new Date());

const coachStaffId = ref(0);
const coachName = ref("私教");
const coachAvatarUrl = ref<string | null>(null);

const profile = ref<MemberPrivateCoachProfile | null>(null);
const selectedDate = ref(todayIso);
const privateLastBookableDate = ref("");
const slots = ref<MemberPrivateCoachTimeSlot[]>([]);
const selectedStart = ref("");
const bookCourseId = ref(0);

const loading = ref(true);
const slotsLoading = ref(false);
const errorMessage = ref("");
const cardSheetOpen = ref(false);
const cardsLoading = ref(false);
const cards = ref<MemberCardWalletSummary[]>([]);
const selectedCardId = ref<number | null>(null);
const booking = ref(false);

const subjectCourses = computed(() => profile.value?.courses ?? []);
const needSubject = computed(
  () => profile.value?.subjectMode === "per_course" && subjectCourses.value.length > 0,
);

const days = computed(() => {
  const end = privateLastBookableDate.value || shiftIso(todayIso, 13);
  const items: Array<{ iso: string; weekdayLabel: string; day: number }> = [];
  let cursor = todayIso;
  while (cursor <= end && items.length < 31) {
    const d = new Date(`${cursor}T00:00:00`);
    items.push({
      iso: cursor,
      weekdayLabel: cursor === todayIso ? "今天" : `周${weekdayCn[d.getDay()] ?? ""}`,
      day: d.getDate(),
    });
    cursor = shiftIso(cursor, 1);
  }
  return items;
});

const slotGroups = computed(() => {
  const groups = [
    { label: "上午", items: [] as MemberPrivateCoachTimeSlot[] },
    { label: "下午", items: [] as MemberPrivateCoachTimeSlot[] },
    { label: "晚上", items: [] as MemberPrivateCoachTimeSlot[] },
  ];
  for (const slot of slots.value) {
    const hour = Number(slot.start.slice(0, 2));
    if (hour < 12) groups[0].items.push(slot);
    else if (hour < 18) groups[1].items.push(slot);
    else groups[2].items.push(slot);
  }
  return groups.filter((g) => g.items.length > 0);
});

async function loadProfile() {
  const context = await ensureMemberContext();
  if (!context || !coachStaffId.value) return null;
  const response = await getMemberPrivateCoachProfile(context.tenantId, context.siteId, coachStaffId.value);
  profile.value = response.data;
  if (response.data.coachName) coachName.value = response.data.coachName;
  if (needSubject.value) {
    bookCourseId.value = subjectCourses.value[0]?.id ?? 0;
  }
  return context;
}

async function loadSlots() {
  if (!coachStaffId.value) return;
  slotsLoading.value = true;
  errorMessage.value = "";
  selectedStart.value = "";
  try {
    const context = await ensureMemberContext();
    if (!context) return;
    const response = await getMemberPrivateCoachTimeSlots(context.tenantId, context.siteId, {
      coachStaffId: coachStaffId.value,
      date: selectedDate.value,
      courseId: needSubject.value ? bookCourseId.value : undefined,
    });
    slots.value = response.data.slots;
    privateLastBookableDate.value = response.data.limits?.privateLastBookableDate ?? privateLastBookableDate.value;
  } catch (error) {
    slots.value = [];
    errorMessage.value = formatApiErrorMessage(error, "可约时段加载失败");
  } finally {
    slotsLoading.value = false;
    loading.value = false;
  }
}

async function bootstrap() {
  loading.value = true;
  try {
    await loadProfile();
    await loadSlots();
  } catch (error) {
    errorMessage.value = formatApiErrorMessage(error, "加载失败");
    loading.value = false;
  }
}

function selectDate(iso: string) {
  if (selectedDate.value === iso) return;
  selectedDate.value = iso;
}

function pickSlot(slot: MemberPrivateCoachTimeSlot) {
  if (!slot.available) return;
  selectedStart.value = slot.start;
  void openCardSheet();
}

async function openCardSheet() {
  if (!selectedStart.value || !coachStaffId.value) return;
  cardSheetOpen.value = true;
  cardsLoading.value = true;
  selectedCardId.value = null;
  try {
    const context = await ensureMemberContext();
    if (!context) return;
    const response = await getMemberPrivateCoachPayableCards(context.tenantId, context.siteId, {
      coachStaffId: coachStaffId.value,
      date: selectedDate.value,
      start: selectedStart.value,
      courseId: needSubject.value ? bookCourseId.value : undefined,
    });
    cards.value = response.data.items;
    selectedCardId.value = cards.value[0]?.id ?? null;
  } catch (error) {
    cards.value = [];
    uni.showToast({ title: formatApiErrorMessage(error, "加载可用卡失败"), icon: "none" });
    cardSheetOpen.value = false;
  } finally {
    cardsLoading.value = false;
  }
}

async function confirmBook() {
  if (!selectedCardId.value || !coachStaffId.value || !selectedStart.value) return;
  booking.value = true;
  try {
    const context = await ensureMemberContext();
    if (!context) return;
    await bookMemberPrivateCoach(context.tenantId, {
      siteId: context.siteId,
      coachStaffId: coachStaffId.value,
      memberCardId: selectedCardId.value,
      date: selectedDate.value,
      start: selectedStart.value,
      courseId: needSubject.value ? bookCourseId.value : undefined,
      commandKey: createCommandKey(),
    });
    cardSheetOpen.value = false;
    uni.showToast({ title: "预约成功", icon: "none" });
    await loadSlots();
  } catch (error) {
    uni.showToast({ title: formatApiErrorMessage(error, "预约失败"), icon: "none" });
  } finally {
    booking.value = false;
  }
}

watch(selectedDate, () => {
  if (!loading.value) void loadSlots();
});

watch(bookCourseId, () => {
  if (!loading.value && needSubject.value) void loadSlots();
});

onLoad((query) => {
  coachStaffId.value = query?.coachId ? Number(query.coachId) : 0;
  if (query?.name) coachName.value = decodeURIComponent(query.name);
  if (query?.avatar) coachAvatarUrl.value = decodeURIComponent(query.avatar);
  if (query?.date && typeof query.date === "string") selectedDate.value = query.date;
  uni.setNavigationBarTitle({ title: coachName.value });
});

onShow(async () => {
  if (await requireMemberAuth()) await bootstrap();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="coach-page">
    <view class="coach-hero">
      <view class="coach-hero-inner">
        <image v-if="coachAvatarUrl" class="coach-photo" :src="coachAvatarUrl" mode="aspectFill" />
        <view v-else class="coach-photo coach-photo--text">{{ coachName.slice(0, 1) }}</view>
        <view class="coach-meta">
          <view class="coach-name">{{ coachName }}</view>
          <view class="coach-tag">私教</view>
        </view>
      </view>
    </view>

    <view class="panel card-sheet">
      <view class="panel-title">选择上课时间</view>

      <scroll-view v-if="needSubject" class="course-scroll" scroll-x :show-scrollbar="false">
        <view class="course-row">
          <view
            v-for="course in subjectCourses"
            :key="course.id"
            class="course-chip"
            :class="{ 'course-chip--active': bookCourseId === course.id }"
            @tap="bookCourseId = course.id"
          >
            {{ course.name }}
          </view>
        </view>
      </scroll-view>

      <scroll-view class="day-scroll" scroll-x :show-scrollbar="false">
        <view class="day-list">
          <view
            v-for="d in days"
            :key="d.iso"
            class="day-item"
            :class="{ 'day-item--active': d.iso === selectedDate }"
            @tap="selectDate(d.iso)"
          >
            <view class="day-week">{{ d.weekdayLabel }}</view>
            <view class="day-num">{{ d.day }}</view>
          </view>
        </view>
      </scroll-view>

      <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '20rpx 0 0' }" />

      <view v-if="slotsLoading" class="list-loading">
        <u-loading-icon mode="circle" />
      </view>

      <template v-else>
        <u-empty
          v-if="!slots.length && !errorMessage"
          mode="list"
          text="~ 当日暂无可约时段 ~"
          margin-top="60"
        />

        <view v-for="group in slotGroups" :key="group.label" class="slot-group">
          <text class="slot-group-label">{{ group.label }}</text>
          <view class="slot-grid">
            <text
              v-for="slot in group.items"
              :key="slot.start"
              class="slot-chip"
              :class="{ active: selectedStart === slot.start, disabled: !slot.available }"
              @tap="pickSlot(slot)"
            >{{ slot.start }}</text>
          </view>
        </view>
      </template>
    </view>

    <u-popup :show="cardSheetOpen" mode="bottom" round="16" @close="cardSheetOpen = false">
      <view class="card-sheet">
        <text class="card-sheet-title">选择会员卡</text>
        <text class="card-sheet-sub">{{ selectedDate }} {{ selectedStart }}</text>
        <view v-if="cardsLoading" class="list-loading"><u-loading-icon mode="circle" /></view>
        <scroll-view v-else scroll-y class="card-list">
          <view
            v-for="card in cards"
            :key="card.id"
            class="card-item"
            :class="{ 'card-item--active': selectedCardId === card.id }"
            @tap="selectedCardId = card.id"
          >
            <text class="card-name">{{ card.name || card.cardNoMasked }}</text>
            <text class="card-meta">{{ card.cardType }}</text>
          </view>
          <u-empty v-if="!cards.length" mode="list" text="暂无可用会员卡" />
        </scroll-view>
        <button class="confirm-btn" :disabled="booking || !selectedCardId" @tap="confirmBook">确认预约</button>
      </view>
    </u-popup>

    <bottom-logo />
  </view>
</template>

<style scoped lang="scss">
.coach-page {
  min-height: 100vh;
  background: $color-page;
}

.coach-hero {
  padding: 40rpx 28rpx 60rpx;
  background: linear-gradient(135deg, #22c788 0%, #1dac75 100%);
}

.coach-hero-inner {
  display: flex;
  align-items: center;
  gap: 24rpx;
}

.coach-photo {
  width: 128rpx;
  height: 128rpx;
  border: 4rpx solid rgba(255, 255, 255, 0.8);
  border-radius: 50%;
  overflow: hidden;
}

.coach-photo--text {
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.9);
  color: $color-text;
  font-size: 52rpx;
  font-weight: 600;
}

.coach-name {
  color: #fff;
  font-size: 40rpx;
  font-weight: 600;
}

.coach-tag {
  display: inline-flex;
  margin-top: 14rpx;
  padding: 4rpx 16rpx;
  background: rgba(255, 255, 255, 0.25);
  border-radius: 8rpx;
  color: #fff;
  font-size: 22rpx;
}

.panel {
  position: relative;
  margin-top: -30rpx;
  min-height: 700rpx;
  padding: 36rpx 28rpx 40rpx;
}

.panel-title {
  font-size: 32rpx;
  font-weight: 600;
}

.course-scroll {
  margin-top: 20rpx;
  white-space: nowrap;
}

.course-row {
  display: inline-flex;
  gap: 16rpx;
}

.course-chip {
  padding: 12rpx 24rpx;
  background: $color-page;
  border-radius: 999rpx;
  font-size: 24rpx;
  color: $color-text-secondary;
}

.course-chip--active {
  background: rgba(34, 199, 136, 0.12);
  color: $color-primary;
}

.day-scroll {
  margin-top: 24rpx;
  white-space: nowrap;
}

.day-list {
  display: inline-flex;
  gap: 16rpx;
}

.day-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 104rpx;
  height: 112rpx;
  background: $color-page;
  border-radius: 16rpx;
  color: $color-text-secondary;
}

.day-item--active {
  background: $color-primary;
  color: #fff;

  .day-num {
    color: #fff;
  }
}

.day-week {
  font-size: 22rpx;
}

.day-num {
  margin-top: 8rpx;
  font-size: 32rpx;
  font-weight: 600;
}

.list-loading {
  display: flex;
  justify-content: center;
  padding: 80rpx 0;
}

.slot-group {
  margin-top: 28rpx;
}

.slot-group-label {
  color: $color-text-muted;
  font-size: 24rpx;
}

.slot-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 16rpx;
  margin-top: 16rpx;
}

.slot-chip {
  width: calc(25% - 12rpx);
  padding: 16rpx 0;
  text-align: center;
  background: $color-page;
  border-radius: 12rpx;
  font-size: 26rpx;

  &.active {
    background: rgba(34, 199, 136, 0.12);
    color: $color-primary;
  }

  &.disabled {
    color: #dadada;
    text-decoration: line-through;
  }
}

.card-sheet {
  padding: 32rpx 28rpx 48rpx;
  max-height: 70vh;
}

.card-sheet-title {
  font-size: 32rpx;
  font-weight: 600;
}

.card-sheet-sub {
  display: block;
  margin-top: 8rpx;
  color: $color-text-muted;
  font-size: 24rpx;
}

.card-list {
  max-height: 420rpx;
  margin: 24rpx 0;
}

.card-item {
  padding: 24rpx;
  margin-bottom: 16rpx;
  border-radius: 12rpx;
  background: $color-page;
}

.card-item--active {
  border: 2rpx solid $color-primary;
}

.card-name {
  font-size: 28rpx;
  font-weight: 500;
}

.card-meta {
  margin-top: 8rpx;
  color: $color-text-muted;
  font-size: 22rpx;
}

.confirm-btn {
  height: 88rpx;
  line-height: 88rpx;
  background: $color-primary;
  border-radius: 44rpx;
  color: #fff;
  font-size: 30rpx;

  &[disabled] {
    opacity: 0.5;
  }
}
</style>
