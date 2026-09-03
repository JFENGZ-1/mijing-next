import type { BookingPolicyConfig } from "@/types/settings";

/** 对标原版 appointSetting selectText 展示文案 */

export function signTimeLabel(minutes: number): string {
  if (minutes <= 0) return "开课时起可签到";
  return `开课前${minutes}分钟起可签到`;
}

export function aheadDaysLabel(days: number, dailyHour?: number, dailyMinute?: number): string {
  if (days <= 0) return "不限制提前预约";
  const hh = String(dailyHour ?? 0).padStart(2, "0");
  const mm = String(dailyMinute ?? 0).padStart(2, "0");
  return `最多提前${days}天预约（每日${hh}:${mm}刷新）`;
}

export function aheadDaysPrivateLabel(days: number): string {
  if (days <= 0) return "不限制提前预约";
  return `最多提前${days}天预约`;
}

export function endAppointTeamLabel(minutes: number): string {
  if (minutes <= 0) return "开课时截止预约";
  return `课前${minutes}分钟停止约课`;
}

export function endAppointPrivateLabel(minutes: number): string {
  if (minutes <= 0) return "开课时可预约";
  return `至少提前${minutes}分钟预约`;
}

export function cancelAppointLabel(minutes: number): string {
  if (minutes <= 0) return "开课前可随时取消";
  return `课前${minutes}分钟停止取消`;
}

export function lineupLabel(enabled: boolean): string {
  return enabled ? "开启" : "关闭";
}

export function showPeopleLabel(enabled: boolean): string {
  return enabled ? "显示" : "不显示";
}

export function cancelOpenCourseLabel(enabled: boolean, minutes: number): string {
  if (!enabled) return "关闭";
  return `开课前${minutes}分钟判断`;
}

export function calendarDaysLabel(days: number): string {
  return `显示未来（含今天）${days}天的课程`;
}

export function slotIntervalLabel(minutes: number): string {
  return `${minutes}分钟`;
}

export function courseRestLabel(minutes: number): string {
  if (minutes <= 0) return "不预留休息时间";
  return `预留${minutes}分钟`;
}

export function beyondTimeLabel(grayOut: boolean): string {
  return grayOut ? "已约时段置灰不可选" : "隐藏不可约时段";
}

export function teamCoursePrivateLabel(mode: BookingPolicyConfig["private"]["groupConflictMode"]): string {
  if (mode === "allow") return "允许重合";
  if (mode === "overlap_warn") return "重合时提示";
  return "禁止重合";
}

export function maxBookingsLabel(limit: number | null): string {
  if (limit == null) return "不限";
  return `每日最多${limit}次`;
}
