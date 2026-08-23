<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchBookingPolicy, updateBookingPolicy } from "@/api/settings";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import FfBottomSheet from "@/components/ff-bottom-sheet/ff-bottom-sheet.vue";
import CustomNav from "@/components/custom-nav/custom-nav.vue";
import FfBottomLogo from "@/components/ff-bottom-logo/ff-bottom-logo.vue";
import type { BookingPolicyConfig } from "@/types/settings";
import {
  aheadDaysLabel,
  aheadDaysPrivateLabel,
  beyondTimeLabel,
  calendarDaysLabel,
  cancelAppointLabel,
  cancelOpenCourseLabel,
  courseRestLabel,
  endAppointPrivateLabel,
  endAppointTeamLabel,
  lineupLabel,
  maxBookingsLabel,
  showPeopleLabel,
  signTimeLabel,
  slotIntervalLabel,
  teamCoursePrivateLabel,
} from "@/utils/booking-policy-display";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const policy = ref<BookingPolicyConfig | null>(null);

const statusBarHeight = uni.getSystemInfoSync().statusBarHeight ?? 20;
const navBarHeight = (() => {
  try {
    const menu = uni.getMenuButtonBoundingClientRect();
    return menu.height + (menu.top - statusBarHeight) * 2;
  } catch {
    return 44;
  }
})();
const navTotalPx = statusBarHeight + navBarHeight;

type SheetKey =
  | "signTime"
  | "aheadTeam"
  | "endTeam"
  | "cancelTeam"
  | "lineup"
  | "showPeople"
  | "autoCancel"
  | "calendar"
  | "aheadPrivate"
  | "endPrivate"
  | "cancelPrivate"
  | "interval"
  | "rest"
  | "beyond"
  | "teamCourse"
  | "maxGroup"
  | "maxPrivate"
  | "";

const activeSheet = ref<SheetKey>("");
const customSelected = ref(false);

const sheetDraft = ref({
  signMinutes: 30,
  aheadDays: 7,
  dailyHour: 0,
  dailyMinute: 0,
  endTeamMinutes: 60,
  cancelTeamMinutes: 120,
  aheadPrivateDays: 14,
  endPrivateMinutes: 60,
  cancelPrivateMinutes: 120,
  interval: 30,
  restMinutes: 0,
  calendarDays: 7,
  autoCancel: false,
  autoCancelMinutes: 180,
  lineup: true,
  showPeople: true,
  grayOut: true,
  groupConflict: "block" as BookingPolicyConfig["private"]["groupConflictMode"],
  maxGroup: "" as string,
  maxPrivate: "" as string,
});

const canWrite = computed(() => session.can("booking.policy.write"));

type Opt = { value: number | string | boolean; label?: string; prefix?: string; suffix?: string };

const specMap: Partial<Record<SheetKey, { options: Opt[] }>> = {
  signTime: { options: [{ value: 0, label: "开课时起可签到" }, { value: 15, label: "开课前15分钟起可签到" }, { value: 30, label: "开课前30分钟起可签到" }, { value: 60, label: "开课前60分钟起可签到" }, { value: 90, label: "开课前90分钟起可签到" }, { value: 120, label: "开课前120分钟起可签到" }, { value: -1, prefix: "开课前", suffix: "分钟起可签到" }] },
  aheadTeam: { options: [{ value: 0, label: "不限制提前预约" }, { value: 7, label: "最多提前7天预约" }, { value: 14, label: "最多提前14天预约" }, { value: 30, label: "最多提前30天预约" }, { value: -1, prefix: "最多提前", suffix: "天预约" }] },
  endTeam: { options: [{ value: 0, label: "开课时截止预约" }, { value: 30, label: "课前30分钟停止约课" }, { value: 60, label: "课前60分钟停止约课" }, { value: 120, label: "课前120分钟停止约课" }, { value: -1, prefix: "课前", suffix: "分钟停止约课" }] },
  cancelTeam: { options: [{ value: 0, label: "开课前可随时取消" }, { value: 30, label: "课前30分钟停止取消" }, { value: 60, label: "课前60分钟停止取消" }, { value: 120, label: "课前120分钟停止取消" }, { value: -1, prefix: "截止开课前", suffix: "分钟，之后不允许会员取消预约" }] },
  lineup: { options: [{ value: true, label: "开启" }, { value: false, label: "关闭" }] },
  showPeople: { options: [{ value: true, label: "显示" }, { value: false, label: "不显示" }] },
  calendar: { options: [{ value: 7, label: "显示未来（含今天）7天的课程" }, { value: 14, label: "显示未来（含今天）14天的课程" }, { value: 30, label: "显示未来（含今天）30天的课程" }, { value: -1, prefix: "显示未来（含今天）", suffix: "天的课程" }] },
  aheadPrivate: { options: [{ value: 0, label: "不限制提前预约" }, { value: 7, label: "最多提前7天预约" }, { value: 14, label: "最多提前14天预约" }, { value: 30, label: "最多提前30天预约" }, { value: -1, prefix: "最多提前", suffix: "天预约" }] },
  endPrivate: { options: [{ value: 0, label: "开课时可预约" }, { value: 30, label: "至少提前30分钟预约" }, { value: 60, label: "至少提前60分钟预约" }, { value: 120, label: "至少提前120分钟预约" }, { value: -1, prefix: "至少提前", suffix: "分钟预约" }] },
  cancelPrivate: { options: [{ value: 0, label: "开课前可随时取消" }, { value: 30, label: "课前30分钟停止取消" }, { value: 60, label: "课前60分钟停止取消" }, { value: 120, label: "课前120分钟停止取消" }, { value: -1, prefix: "截止开课前", suffix: "分钟，之后不允许会员取消预约" }] },
  interval: { options: [{ value: 5, label: "5分钟" }, { value: 10, label: "10分钟" }, { value: 15, label: "15分钟" }, { value: 20, label: "20分钟" }, { value: 30, label: "30分钟" }, { value: 45, label: "45分钟" }, { value: 60, label: "60分钟" }] },
  rest: { options: [{ value: 0, label: "不预留休息时间" }, { value: 5, label: "预留5分钟" }, { value: 10, label: "预留10分钟" }, { value: 15, label: "预留15分钟" }, { value: 30, label: "预留30分钟" }, { value: -1, prefix: "预留", suffix: "分钟" }] },
  beyond: { options: [{ value: true, label: "已约时段置灰不可选" }, { value: false, label: "隐藏不可约时段" }] },
  teamCourse: { options: [{ value: "block", label: "禁止与团课重合" }, { value: "allow", label: "允许重合（仅已约团课占时）" }, { value: "overlap_warn", label: "重合时提示（员工可确认代约）" }] },
};

const activeValue = computed<number | string | boolean | null>({
  get() {
    const d = sheetDraft.value;
    switch (activeSheet.value) {
      case "signTime": return d.signMinutes;
      case "aheadTeam": return d.aheadDays;
      case "endTeam": return d.endTeamMinutes;
      case "cancelTeam": return d.cancelTeamMinutes;
      case "lineup": return d.lineup;
      case "showPeople": return d.showPeople;
      case "calendar": return d.calendarDays;
      case "aheadPrivate": return d.aheadPrivateDays;
      case "endPrivate": return d.endPrivateMinutes;
      case "cancelPrivate": return d.cancelPrivateMinutes;
      case "interval": return d.interval;
      case "rest": return d.restMinutes;
      case "beyond": return d.grayOut;
      case "teamCourse": return d.groupConflict;
      default: return null;
    }
  },
  set(v) {
    const d = sheetDraft.value;
    const n = Number(v);
    switch (activeSheet.value) {
      case "signTime": d.signMinutes = n; break;
      case "aheadTeam": d.aheadDays = n; break;
      case "endTeam": d.endTeamMinutes = n; break;
      case "cancelTeam": d.cancelTeamMinutes = n; break;
      case "lineup": d.lineup = !!v; break;
      case "showPeople": d.showPeople = !!v; break;
      case "calendar": d.calendarDays = n; break;
      case "aheadPrivate": d.aheadPrivateDays = n; break;
      case "endPrivate": d.endPrivateMinutes = n; break;
      case "cancelPrivate": d.cancelPrivateMinutes = n; break;
      case "interval": d.interval = n; break;
      case "rest": d.restMinutes = n; break;
      case "beyond": d.grayOut = !!v; break;
      case "teamCourse": d.groupConflict = String(v) as BookingPolicyConfig["private"]["groupConflictMode"]; break;
      default: break;
    }
  },
});

const currentOptions = computed<Opt[]>(() => specMap[activeSheet.value]?.options ?? []);

function isCustom(opt: Opt): boolean {
  return opt.value === -1;
}

function isActive(opt: Opt): boolean {
  if (isCustom(opt)) return customSelected.value;
  return !customSelected.value && opt.value === activeValue.value;
}

function selectOption(opt: Opt) {
  if (isCustom(opt)) {
    customSelected.value = true;
    return;
  }
  customSelected.value = false;
  activeValue.value = opt.value;
}

const sheetTitle = computed(() => {
  const map: Record<string, string> = {
    signTime: "系统签到时间",
    aheadTeam: "提前预约时间",
    endTeam: "截止预约时间",
    cancelTeam: "取消预约时间",
    lineup: "排队候补",
    showPeople: "是否显示已约人数",
    autoCancel: "未满足最低开课人数，自动取消开课",
    calendar: "显示几天的课表",
    aheadPrivate: "提前预约时间",
    endPrivate: "截止预约时间",
    cancelPrivate: "取消预约时间",
    interval: "时间列表的时间间隔",
    rest: "课前休息与准备时间",
    beyond: "已预约时间置灰",
    teamCourse: "与团课重合时",
    maxGroup: "会员每日团课预约上限",
    maxPrivate: "会员每日私教预约上限",
  };
  return map[activeSheet.value] ?? "";
});

const sheetTips = computed(() => {
  const map: Record<string, string> = {
    autoCancel: "当已约人数未满足最低开课人数时，由系统自动取消该课程并下发课程取消通知给已预约的会员；注：最高可设置课前180分钟",
    aheadTeam: "每日刷新时刻前，会员最远可约天数少 1 天",
    teamCourse: "会员端不可确认重合；员工代约/改约可确认（overlap_warn）",
  };
  return map[activeSheet.value] ?? "";
});

const sheetHeight = computed(() => {
  return activeSheet.value === "autoCancel" ? 900 : 1000;
});

function pad2(n: number): string {
  return String(Number(n) || 0).padStart(2, "0");
}

const refreshTimeStr = computed(() => `${pad2(sheetDraft.value.dailyHour)}:${pad2(sheetDraft.value.dailyMinute)}`);

function onRefreshTimeChange(e: { detail: { value: string } }) {
  const [h, m] = e.detail.value.split(":").map((x) => Number(x) || 0);
  sheetDraft.value.dailyHour = Math.min(23, Math.max(0, h));
  sheetDraft.value.dailyMinute = Math.min(59, Math.max(0, m));
}

function onCustomInput(e: { detail: { value: string } }) {
  customSelected.value = true;
  activeValue.value = Number(e.detail.value) || 0;
}

function onRadioGroupChange(value: string | number | boolean) {
  if (String(value) === "__custom__") {
    customSelected.value = true;
    return;
  }
  const opts = currentOptions.value;
  const match = opts.find((opt) => !isCustom(opt) && String(opt.value) === String(value));
  if (!match) return;
  customSelected.value = false;
  activeValue.value = match.value;
}

function onAutoCancelMinutesInput(e: { detail: { value: string } }) {
  sheetDraft.value.autoCancelMinutes = Number(e.detail.value) || 0;
}

function syncCustomSelected(key: SheetKey) {
  const opts = specMap[key]?.options ?? [];
  const hasCustom = opts.some((o) => isCustom(o));
  if (!hasCustom) {
    customSelected.value = false;
    return;
  }
  const v =
    key === "signTime" ? sheetDraft.value.signMinutes
    : key === "aheadTeam" ? sheetDraft.value.aheadDays
    : key === "endTeam" ? sheetDraft.value.endTeamMinutes
    : key === "cancelTeam" ? sheetDraft.value.cancelTeamMinutes
    : key === "calendar" ? sheetDraft.value.calendarDays
    : key === "aheadPrivate" ? sheetDraft.value.aheadPrivateDays
    : key === "endPrivate" ? sheetDraft.value.endPrivateMinutes
    : key === "cancelPrivate" ? sheetDraft.value.cancelPrivateMinutes
    : key === "rest" ? sheetDraft.value.restMinutes
    : null;
  customSelected.value = v != null && !opts.some((o) => !isCustom(o) && o.value === v);
}

function openSheet(key: SheetKey) {
  if (!policy.value || !canWrite.value) return;
  const p = policy.value;
  if (key === "signTime") sheetDraft.value.signMinutes = p.group.signMinutesBeforeStart;
  else if (key === "aheadTeam") {
    sheetDraft.value.aheadDays = p.group.advanceBookingDays;
    sheetDraft.value.dailyHour = p.group.advanceBookingDailyCutoffHour;
    sheetDraft.value.dailyMinute = p.group.advanceBookingDailyCutoffMinute;
  } else if (key === "endTeam") sheetDraft.value.endTeamMinutes = p.group.bookingCutoffMinutesBeforeStart;
  else if (key === "cancelTeam") sheetDraft.value.cancelTeamMinutes = p.group.cancelCutoffMinutesBeforeStart;
  else if (key === "lineup") sheetDraft.value.lineup = p.group.waitlistEnabled;
  else if (key === "showPeople") sheetDraft.value.showPeople = p.group.showBookedCount;
  else if (key === "autoCancel") {
    sheetDraft.value.autoCancel = p.group.autoCancelUnderMinStudentsEnabled;
    sheetDraft.value.autoCancelMinutes = p.group.autoCancelUnderMinStudentsMinutesBeforeStart;
  } else if (key === "calendar") sheetDraft.value.calendarDays = p.group.calendarDisplayDays;
  else if (key === "aheadPrivate") sheetDraft.value.aheadPrivateDays = p.private.advanceBookingDays;
  else if (key === "endPrivate") sheetDraft.value.endPrivateMinutes = p.private.minimumLeadMinutes;
  else if (key === "cancelPrivate") sheetDraft.value.cancelPrivateMinutes = p.private.cancelCutoffMinutesBeforeStart;
  else if (key === "interval") sheetDraft.value.interval = p.private.slotIntervalMinutes;
  else if (key === "rest") sheetDraft.value.restMinutes = p.private.preparationMinutes;
  else if (key === "beyond") sheetDraft.value.grayOut = p.private.grayOutBookedSlots;
  else if (key === "teamCourse") sheetDraft.value.groupConflict = p.private.groupConflictMode;
  else if (key === "maxGroup") sheetDraft.value.maxGroup = p.group.maxBookingsPerDay == null ? "" : String(p.group.maxBookingsPerDay);
  else if (key === "maxPrivate") sheetDraft.value.maxPrivate = p.private.maxBookingsPerDay == null ? "" : String(p.private.maxBookingsPerDay);
  syncCustomSelected(key);
  activeSheet.value = key;
}

function closeSheet() {
  activeSheet.value = "";
}

async function saveSingle() {
  const p = policy.value;
  if (!p || !session.currentSiteId) return;
  saving.value = true;
  errorMessage.value = "";
  try {
    policy.value = await updateBookingPolicy(session.currentSiteId, p);
    uni.showToast({ title: "保存成功", icon: "none" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
    uni.showToast({ title: errorMessage.value, icon: "none" });
  } finally {
    saving.value = false;
  }
}

function applySheet() {
  const p = policy.value;
  if (!p) return;
  const d = sheetDraft.value;
  switch (activeSheet.value) {
    case "signTime": p.group.signMinutesBeforeStart = Math.max(0, Number(d.signMinutes)); break;
    case "aheadTeam":
      p.group.advanceBookingDays = Math.max(0, Number(d.aheadDays));
      p.group.advanceBookingDailyCutoffHour = Math.min(23, Math.max(0, Number(d.dailyHour)));
      p.group.advanceBookingDailyCutoffMinute = Math.min(59, Math.max(0, Number(d.dailyMinute)));
      break;
    case "endTeam": p.group.bookingCutoffMinutesBeforeStart = Math.max(0, Number(d.endTeamMinutes)); break;
    case "cancelTeam": p.group.cancelCutoffMinutesBeforeStart = Math.max(0, Number(d.cancelTeamMinutes)); break;
    case "lineup": p.group.waitlistEnabled = d.lineup; break;
    case "showPeople": p.group.showBookedCount = d.showPeople; break;
    case "autoCancel":
      p.group.autoCancelUnderMinStudentsEnabled = d.autoCancel;
      p.group.autoCancelUnderMinStudentsMinutesBeforeStart = Math.min(180, Math.max(0, Number(d.autoCancelMinutes)));
      break;
    case "calendar": p.group.calendarDisplayDays = Math.max(1, Number(d.calendarDays)); break;
    case "aheadPrivate": p.private.advanceBookingDays = Math.max(0, Number(d.aheadPrivateDays)); break;
    case "endPrivate": p.private.minimumLeadMinutes = Math.max(0, Number(d.endPrivateMinutes)); break;
    case "cancelPrivate": p.private.cancelCutoffMinutesBeforeStart = Math.max(0, Number(d.cancelPrivateMinutes)); break;
    case "interval": p.private.slotIntervalMinutes = Math.max(5, Number(d.interval)); break;
    case "rest": p.private.preparationMinutes = Math.max(0, Number(d.restMinutes)); break;
    case "beyond": p.private.grayOutBookedSlots = d.grayOut; break;
    case "teamCourse": p.private.groupConflictMode = d.groupConflict; break;
    case "maxGroup": p.group.maxBookingsPerDay = d.maxGroup === "" ? null : Math.max(1, Number(d.maxGroup)); break;
    case "maxPrivate": p.private.maxBookingsPerDay = d.maxPrivate === "" ? null : Math.max(1, Number(d.maxPrivate)); break;
    default: break;
  }
  closeSheet();
  saveSingle();
}

const display = computed(() => {
  const p = policy.value;
  if (!p) return null;
  return {
    signTime: signTimeLabel(p.group.signMinutesBeforeStart),
    aheadTeam: aheadDaysLabel(p.group.advanceBookingDays, p.group.advanceBookingDailyCutoffHour, p.group.advanceBookingDailyCutoffMinute),
    endTeam: endAppointTeamLabel(p.group.bookingCutoffMinutesBeforeStart),
    cancelTeam: cancelAppointLabel(p.group.cancelCutoffMinutesBeforeStart),
    lineup: lineupLabel(p.group.waitlistEnabled),
    showPeople: showPeopleLabel(p.group.showBookedCount),
    autoCancel: cancelOpenCourseLabel(p.group.autoCancelUnderMinStudentsEnabled, p.group.autoCancelUnderMinStudentsMinutesBeforeStart),
    calendar: calendarDaysLabel(p.group.calendarDisplayDays),
    aheadPrivate: aheadDaysPrivateLabel(p.private.advanceBookingDays),
    endPrivate: endAppointPrivateLabel(p.private.minimumLeadMinutes),
    cancelPrivate: cancelAppointLabel(p.private.cancelCutoffMinutesBeforeStart),
    interval: slotIntervalLabel(p.private.slotIntervalMinutes),
    rest: courseRestLabel(p.private.preparationMinutes),
    beyond: beyondTimeLabel(p.private.grayOutBookedSlots),
    teamCourse: teamCoursePrivateLabel(p.private.groupConflictMode),
    maxGroup: maxBookingsLabel(p.group.maxBookingsPerDay),
    maxPrivate: maxBookingsLabel(p.private.maxBookingsPerDay),
  };
});

async function load() {
  if (!session.currentSiteId || !session.can("booking.policy.read")) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    policy.value = await fetchBookingPolicy(session.currentSiteId);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "预约设置加载失败";
    uni.showToast({ title: errorMessage.value, icon: "none" });
  } finally {
    loading.value = false;
  }
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <CustomNav text="预约设置" />
  <view class="placeholder-view" :style="{ height: `${navTotalPx}px` }" />

  <view v-if="!loading && policy && display" class="appoint-main" :style="{ marginTop: '0' }">
    <u-empty v-if="!session.can('booking.policy.read')" mode="permission" text="暂无查看权限" />

    <view v-else class="content">
      <view class="setting_wrap">
      <view class="top">
        <u-icon name="bell" size="14" color="#C96B30" />
        <view class="top-info">以下设置仅对会员约课端起作用，教练端作为管理端不受限制</view>
      </view>

      <view class="setting_group first">
        <view class="setting_title">
          <view class="title_color" />
          <text class="title_text">签到设置</text>
        </view>
        <view class="item_wrap">
          <view class="item no_border" @tap="openSheet('signTime')">
            <view class="item_title">系统签到时间</view>
            <view class="item_select">
              <text class="select_text">{{ display.signTime }}</text>
              <view class="chevron" />
            </view>
          </view>
        </view>
      </view>

      <view class="setting_group second">
        <view class="setting_title">
          <view class="title_color" />
          <text class="title_text">约团课</text>
        </view>
        <view class="item_wrap">
          <view class="item" @tap="openSheet('aheadTeam')">
            <view class="item_title">提前预约时间</view>
            <view class="item_select"><text class="select_text">{{ display.aheadTeam }}</text><view class="chevron" /></view>
          </view>
          <view class="item" @tap="openSheet('endTeam')">
            <view class="item_title">截止预约时间</view>
            <view class="item_select"><text class="select_text">{{ display.endTeam }}</text><view class="chevron" /></view>
          </view>
          <view class="item" @tap="openSheet('cancelTeam')">
            <view class="item_title">取消预约时间</view>
            <view class="item_select"><text class="select_text">{{ display.cancelTeam }}</text><view class="chevron" /></view>
          </view>
          <view class="item" @tap="openSheet('lineup')">
            <view class="item_title">排队候补</view>
            <view class="item_select"><text class="select_text">{{ display.lineup }}</text><view class="chevron" /></view>
          </view>
          <view class="item" @tap="openSheet('showPeople')">
            <view class="item_title">是否显示已约人数</view>
            <view class="item_select"><text class="select_text">{{ display.showPeople }}</text><view class="chevron" /></view>
          </view>
          <view class="item" @tap="openSheet('autoCancel')">
            <view class="item_title">未满足最低开课人数，自动取消开课</view>
            <view class="item_select"><text class="select_text">{{ display.autoCancel }}</text><view class="chevron" /></view>
          </view>
          <view class="item no_border" @tap="openSheet('calendar')">
            <view class="item_title">显示几天的课表</view>
            <view class="item_select"><text class="select_text">{{ display.calendar }}</text><view class="chevron" /></view>
          </view>
        </view>
      </view>

      <view class="setting_group last">
        <view class="setting_title">
          <view class="title_color" />
          <text class="title_text">约私教</text>
        </view>
        <view class="item_wrap">
          <view class="item" @tap="openSheet('aheadPrivate')">
            <view class="item_title">提前预约时间</view>
            <view class="item_select"><text class="select_text">{{ display.aheadPrivate }}</text><view class="chevron" /></view>
          </view>
          <view class="item" @tap="openSheet('endPrivate')">
            <view class="item_title">截止预约时间</view>
            <view class="item_select"><text class="select_text">{{ display.endPrivate }}</text><view class="chevron" /></view>
          </view>
          <view class="item" @tap="openSheet('cancelPrivate')">
            <view class="item_title">取消预约时间</view>
            <view class="item_select"><text class="select_text">{{ display.cancelPrivate }}</text><view class="chevron" /></view>
          </view>
          <view class="item" @tap="openSheet('interval')">
            <view class="item_title">时间列表的时间间隔</view>
            <view class="item_select"><text class="select_text">{{ display.interval }}</text><view class="chevron" /></view>
          </view>
          <view class="item" @tap="openSheet('rest')">
            <view class="item_title">课前休息与准备时间</view>
            <view class="item_select"><text class="select_text">{{ display.rest }}</text><view class="chevron" /></view>
          </view>
          <view class="item" @tap="openSheet('beyond')">
            <view class="item_title">已预约时间置灰</view>
            <view class="item_select"><text class="select_text">{{ display.beyond }}</text><view class="chevron" /></view>
          </view>
          <view class="item no_border" @tap="openSheet('teamCourse')">
            <view class="item_title">与团课重合时</view>
            <view class="item_select"><text class="select_text">{{ display.teamCourse }}</text><view class="chevron" /></view>
          </view>
        </view>
      </view>

      <view class="setting_group extension">
        <view class="setting_title">
          <view class="title_color extension_color" />
          <text class="title_text">扩展（新版）</text>
        </view>
        <view class="item_wrap">
          <view class="item" @tap="openSheet('maxGroup')">
            <view class="item_title">会员每日团课预约上限</view>
            <view class="item_select"><text class="select_text">{{ display.maxGroup }}</text><view class="chevron" /></view>
          </view>
          <view class="item no_border" @tap="openSheet('maxPrivate')">
            <view class="item_title">会员每日私教预约上限</view>
            <view class="item_select"><text class="select_text">{{ display.maxPrivate }}</text><view class="chevron" /></view>
          </view>
        </view>
        <view class="extension_hint">原版预约设置页无此项；后端已支持每日预约上限策略。</view>
      </view>
      </view>

      <FfBottomLogo />
    </view>

    <!-- 通用单选弹层（对标原版 radio + hasParam 输入） -->
    <FfBottomSheet
      :show="!!activeSheet && activeSheet !== 'autoCancel' && activeSheet !== 'maxGroup' && activeSheet !== 'maxPrivate'"
      :title="sheetTitle"
      :tips="sheetTips"
      :height-rpx="sheetHeight"
      flush-body
      @update:show="(v) => !v && closeSheet()"
      @confirm="applySheet"
    >
      <view class="main_box_first">
        <u-radio-group
          :model-value="customSelected ? '__custom__' : String(activeValue)"
          placement="column"
          active-color="#fbd128"
          inactive-color="#c8c9cc"
          icon-size="14"
          label-size="28rpx"
          @change="onRadioGroupChange"
        >
          <view
            v-for="(opt, idx) in currentOptions"
            :key="`${activeSheet}-${idx}`"
            class="select_item"
          >
            <u-radio :name="isCustom(opt) ? '__custom__' : String(opt.value)" label=" ">
              <template #label>
                <view class="radio-main" :class="{ active: isActive(opt), wrap: isCustom(opt) && (activeSheet === 'cancelTeam' || activeSheet === 'cancelPrivate' || activeSheet === 'aheadTeam' || activeSheet === 'rest') }">
                  <template v-if="isCustom(opt)">
                    <text>{{ opt.prefix }}</text>
                    <input
                      class="com-input"
                      type="number"
                      :value="String(activeValue ?? '')"
                      :disabled="!isActive(opt)"
                      @tap.stop
                      @input="onCustomInput"
                    />
                    <text>{{ opt.suffix }}</text>
                    <view v-if="activeSheet === 'aheadTeam' && isActive(opt)" class="appoint-tips">
                      <text>每日 {{ refreshTimeStr }} 开放最新可约课程</text>
                      <picker mode="time" :value="refreshTimeStr" @change="onRefreshTimeChange">
                        <text class="modify-button">修改</text>
                      </picker>
                    </view>
                    <view v-if="activeSheet === 'rest' && isActive(opt)" class="tips-wrap">
                      <text>受【时间列表的时间间隔】设置影响：</text>
                      <text>若间隔是10分钟，则最少时间为10分钟</text>
                      <text>若间隔是15分钟，则最少时间为15分钟</text>
                      <text>若间隔是30分钟，则最少时间为30分钟</text>
                    </view>
                  </template>
                  <text v-else>{{ opt.label }}</text>
                </view>
              </template>
            </u-radio>
          </view>
          <view v-if="activeSheet === 'signTime'" class="select_item disabled">
            <u-radio name="__disabled__" disabled label=" ">
              <template #label>
                <view class="radio-main disabled">由会员自主签到(暂不开放)</view>
              </template>
            </u-radio>
          </view>
        </u-radio-group>
      </view>
    </FfBottomSheet>

    <!-- 自动取消开课：对标原版 main_box_last -->
    <FfBottomSheet
      :show="activeSheet === 'autoCancel'"
      :title="sheetTitle"
      :tips="sheetTips"
      :height-rpx="900"
      flush-body
      @update:show="(v) => !v && closeSheet()"
      @confirm="applySheet"
    >
      <view class="main_box_last">
        <u-radio-group
          :model-value="sheetDraft.autoCancel ? '1' : '0'"
          placement="column"
          active-color="#fbd128"
          inactive-color="#c8c9cc"
          icon-size="14"
          label-size="28rpx"
          @change="(v: string) => { sheetDraft.autoCancel = v === '1' }"
        >
          <view class="auto-cancel-row">
            <u-radio name="0" label=" ">
              <template #label>
                <text class="radio-main" :class="{ active: !sheetDraft.autoCancel }">关闭</text>
              </template>
            </u-radio>
          </view>
          <view class="auto-cancel-row">
            <u-radio name="1" label=" ">
              <template #label>
                <view class="radio-main" :class="{ active: sheetDraft.autoCancel }">
                  <text>在课前</text>
                  <input
                    class="com-input"
                    type="number"
                    :value="String(sheetDraft.autoCancelMinutes)"
                    :disabled="!sheetDraft.autoCancel"
                    @tap.stop
                    @input="onAutoCancelMinutesInput"
                  />
                  <text>分钟进行判断</text>
                </view>
              </template>
            </u-radio>
          </view>
        </u-radio-group>
      </view>
    </FfBottomSheet>

    <FfBottomSheet
      :show="activeSheet === 'maxGroup'"
      title="会员每日团课预约上限"
      :height-rpx="800"
      @update:show="(v) => !v && closeSheet()"
      @confirm="applySheet"
    >
      <view class="custom-row">
        <text>留空表示不限</text>
        <u-input v-model="sheetDraft.maxGroup" type="number" placeholder="不限" />
      </view>
    </FfBottomSheet>

    <FfBottomSheet
      :show="activeSheet === 'maxPrivate'"
      title="会员每日私教预约上限"
      :height-rpx="800"
      @update:show="(v) => !v && closeSheet()"
      @confirm="applySheet"
    >
      <view class="custom-row">
        <text>留空表示不限</text>
        <u-input v-model="sheetDraft.maxPrivate" type="number" placeholder="不限" />
      </view>
    </FfBottomSheet>
  </view>
</template>

<style scoped lang="scss">
.placeholder-view {
  width: 100%;
  background: #fbd128;
}

.appoint-main {
  min-height: calc(100vh - 100px);
  background: #fbd128;
}

.content {
  min-height: 100%;
}

.setting_wrap {
  min-height: 70vh;
  padding-bottom: 24rpx;
  background: #f5f5f5;
  background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAD/CAYAAADMmJcqAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAKUlEQVR4nO3BMQEAAADCoPVP7WsIoAAAAAAAAAAAAAAAAAAAAOA1v9QAATX68LsAAAAASUVORK5CYII=");
  background-position: top;
  background-repeat: repeat-x;
  border-top-left-radius: 21rpx;
  border-top-right-radius: 21rpx;
  overflow: hidden;
}

.top {
  display: flex;
  align-items: center;
  width: 694rpx;
  height: 61rpx;
  margin: 29rpx 28rpx 22rpx;
  padding: 0 20rpx;
  box-sizing: border-box;
  background: #fef9de;
  border-radius: 10rpx;
  border-top-left-radius: 21rpx;
  border-top-right-radius: 21rpx;
  overflow: hidden;
}

.top-info {
  margin-left: 8rpx;
  color: #c96a2f;
  font-size: 22rpx;
  flex: 1;
}

.setting_group {
  background: #fff;
  border-radius: 21rpx;
  margin: 0 15rpx;
}

.setting_group.first {
  margin-top: 35rpx;
}

.setting_group.second {
  margin-top: 20rpx;
  margin-bottom: 20rpx;
  padding-bottom: 15rpx;
}

.setting_group.last {
  padding-bottom: 10rpx;
}

.setting_group.extension {
  margin-top: 20rpx;
  padding-bottom: 16rpx;
}

.setting_title {
  display: flex;
  align-items: center;
  height: 46rpx;
  padding: 33rpx 0 4rpx;
  font-weight: 500;
}

.title_color {
  width: 17rpx;
  height: 100%;
  background: #22c788;
  border-top-right-radius: 14rpx;
  border-bottom-right-radius: 14rpx;
}

.extension_color {
  background: #909399;
}

.title_text {
  margin-left: 10rpx;
  font-size: 28rpx;
}

.item {
  display: flex;
  height: 112rpx;
  margin: 0 27rpx;
  position: relative;
  box-sizing: content-box;
}

.item:after {
  content: " ";
  position: absolute;
  left: -50%;
  top: -50%;
  width: 200%;
  height: 200%;
  border-bottom: 1px solid #f0f0f0;
  transform: scale(0.5);
  z-index: 2;
  box-sizing: border-box;
  pointer-events: none;
}

.item.no_border:after {
  border: none !important;
}

.item_title {
  flex: 1;
  display: flex;
  align-items: center;
  font-size: 28rpx;
  font-weight: 400;
}

.item_select {
  display: flex;
  align-items: center;
  color: #7e7e7e;
  font-size: 25rpx;
}

.select_text {
  max-width: 350rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chevron {
  margin-left: 12rpx;
  width: 11rpx;
  height: 11rpx;
  border-top: 3rpx solid #c0c0c0;
  border-right: 3rpx solid #c0c0c0;
  transform: rotate(45deg);
  flex-shrink: 0;
}

.extension_hint {
  margin: 8rpx 27rpx 0;
  color: #999;
  font-size: 22rpx;
}

.main_box_first {
  padding: 45rpx 50rpx;
}

.main_box_last {
  padding: 45rpx 60rpx;
}

.select_item,
.auto-cancel-row {
  display: flex;
  align-items: flex-start;
  margin-bottom: 30rpx;
}

.select_item :deep(.u-radio),
.auto-cancel-row :deep(.u-radio) {
  flex: 1;
  margin-top: 0;
  margin-bottom: 0;
}

.select_item :deep(.u-radio__icon-wrap),
.auto-cancel-row :deep(.u-radio__icon-wrap) {
  margin-top: 6rpx;
  margin-right: 19rpx;
}

.select_item :deep(.u-radio__icon-wrap--checked),
.auto-cancel-row :deep(.u-radio__icon-wrap--checked) {
  background-color: #fbd128;
  border-color: #fbd128;
}

.select_item :deep(.u-radio__icon-wrap__icon),
.auto-cancel-row :deep(.u-radio__icon-wrap__icon) {
  color: #181818;
}

.select_item:last-of-type,
.auto-cancel-row:last-of-type {
  margin-bottom: 0;
}

.select_item.disabled {
  opacity: 1;
}
.radio-main {
  flex: 1;
  display: flex;
  align-items: flex-start;
  flex-wrap: nowrap;
  color: #7e7e7e;
  font-size: 28rpx;
  line-height: 1.5;
}

.radio-main.wrap {
  flex-wrap: wrap;
}

.radio-main.active {
  color: #181818;
  font-weight: 500;
}

.radio-main.disabled {
  color: #dadada !important;
}

.com-input {
  width: 75rpx;
  height: 55rpx;
  margin: 0 10rpx;
  text-align: center;
  color: #181818;
  font-size: 28rpx;
  border-bottom: 1px solid #e5e5e5;
}

.appoint-tips {
  display: flex;
  width: 100%;
  padding-top: 20rpx;
  color: #c96a2f;
  font-size: 26rpx;
  font-weight: 400;
  line-height: 36rpx;
}

.modify-button {
  margin-left: 12rpx;
  color: #003d82;
  text-decoration: underline;
}

.tips-wrap {
  display: flex;
  flex-direction: column;
  width: 100%;
  padding-top: 20rpx;
  color: #dc3c5c;
  font-size: 22rpx;
  font-weight: 400;
  line-height: 36rpx;
}

.custom-row {
  display: flex;
  flex-direction: column;
  gap: 12rpx;
  font-size: 26rpx;
}
</style>
