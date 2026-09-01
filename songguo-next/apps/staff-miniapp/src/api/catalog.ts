import { useApiClient } from "@/api/client";
import type {
  CourseCatalogList,
  CourseDetail,
  CourseUpdatePayload,
  CourseUpsertPayload,
  RoomCatalogItem,
  RoomCatalogList,
  RoomUpdatePayload,
  RoomUpsertPayload,
} from "@/types/catalog";
import type { CourseType } from "@/types/scheduling";

function sitePath(siteId: number, suffix: string) {
  return `/staff/sites/${siteId}${suffix}`;
}

function coursePath(siteId: number, courseId?: number, suffix = "") {
  const base = sitePath(siteId, "/courses");
  return courseId == null ? base : `${base}/${courseId}${suffix}`;
}

function buildQuery(params: Record<string, string | number | undefined>): string {
  const parts = Object.entries(params)
    .filter(([, value]) => value !== undefined && value !== "")
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`);
  return parts.length ? `?${parts.join("&")}` : "";
}

export async function fetchStaffCourseCatalog(
  siteId: number,
  page = 1,
  perPage = 50,
  q?: string,
  courseType?: CourseType,
) {
  const response = await useApiClient().request<CourseCatalogList>(
    `${coursePath(siteId)}${buildQuery({ page, perPage, q, courseType })}`,
  );
  return response.data;
}

export async function fetchAllStaffCourseCatalog(siteId: number, q?: string, courseType?: CourseType) {
  const items: CourseCatalogList["items"] = [];
  let page = 1;
  let lastPage = 1;
  do {
    const response = await fetchStaffCourseCatalog(siteId, page, 50, q, courseType);
    items.push(...response.items);
    lastPage = response.pagination?.lastPage ?? page;
    page += 1;
  } while (page <= lastPage);
  return items;
}

export async function fetchStaffCourse(siteId: number, courseId: number) {
  const response = await useApiClient().request<CourseDetail>(coursePath(siteId, courseId));
  return response.data;
}

export async function createStaffCourse(siteId: number, payload: CourseUpsertPayload) {
  const response = await useApiClient().request<CourseDetail>(coursePath(siteId), {
    method: "POST",
    data: payload,
  });
  return response.data;
}

export async function updateStaffCourse(siteId: number, courseId: number, payload: CourseUpdatePayload) {
  const response = await useApiClient().request<CourseDetail>(coursePath(siteId, courseId), {
    method: "PUT",
    data: payload,
  });
  return response.data;
}

export async function archiveStaffCourse(siteId: number, courseId: number) {
  const response = await useApiClient().request<CourseDetail>(coursePath(siteId, courseId, "/archive"), {
    method: "POST",
  });
  return response.data;
}

export async function restoreStaffCourse(siteId: number, courseId: number) {
  const response = await useApiClient().request<CourseDetail>(coursePath(siteId, courseId, "/restore"), {
    method: "POST",
  });
  return response.data;
}

function roomPath(siteId: number, roomId?: number, suffix = "") {
  const base = sitePath(siteId, "/rooms");
  return roomId == null ? base : `${base}/${roomId}${suffix}`;
}

export async function fetchStaffRoomCatalog(siteId: number) {
  const response = await useApiClient().request<RoomCatalogList>(roomPath(siteId));
  return response.data;
}

export async function fetchStaffRoom(siteId: number, roomId: number) {
  const response = await useApiClient().request<RoomCatalogItem>(roomPath(siteId, roomId));
  return response.data;
}

export async function createStaffRoom(siteId: number, payload: RoomUpsertPayload) {
  const response = await useApiClient().request<RoomCatalogItem>(roomPath(siteId), {
    method: "POST",
    data: payload,
  });
  return response.data;
}

export async function updateStaffRoom(siteId: number, roomId: number, payload: RoomUpdatePayload) {
  const response = await useApiClient().request<RoomCatalogItem>(roomPath(siteId, roomId), {
    method: "PUT",
    data: payload,
  });
  return response.data;
}

export async function archiveStaffRoom(siteId: number, roomId: number) {
  const response = await useApiClient().request<RoomCatalogItem>(roomPath(siteId, roomId, "/archive"), {
    method: "POST",
  });
  return response.data;
}

// 课程删除前检查（原版 checkHasPlan：有未来排课时警示）
export async function fetchCourseDeletePreflight(siteId: number, courseId: number) {
  const response = await useApiClient().request<{ courseId: number; futureSessionCount: number }>(
    `/staff/sites/${siteId}/courses/${courseId}/delete-preflight`,
  );
  return response.data;
}

// ================= 私教档案（对标原版 drainer：预约时间制） =================
export interface CoachBookingWindow {
  days: number[]; // 1=周一 … 7=周日
  start: string; // "08:00"
  end: string; // "21:00"
}

// 课目卡扣费（对标原版 feeList：cardId + deductAmount）
export interface CoachPrivateFee {
  cardProductId: number;
  cardName?: string | null;
  deductAmount?: string | number | null;
}

export interface CoachPrivateProfileCourse {
  id: number;
  name: string;
  durationMinutes: number;
  version: number;
  feeList: CoachPrivateFee[];
}

export interface CoachPrivateProfile {
  id: number;
  coachStaffId: number;
  coachName: string | null;
  tagText: string | null;
  experience: string | null;
  specialty: string | null;
  bookingWindows: CoachBookingWindow[];
  subjectMode: "uniform" | "per_course";
  uniformDurationMinutes: number;
  uniformCourseId: number | null;
  uniformFeeList: CoachPrivateFee[];
  courses: CoachPrivateProfileCourse[];
  version: number;
}

// 一次性保存（对标原版 savePrivateCourse）
export interface CoachPrivateSaveFullPayload {
  profileId?: number;
  version?: number;
  coachStaffId?: number;
  tagText?: string | null;
  experience?: string | null;
  specialty?: string | null;
  bookingWindows: CoachBookingWindow[];
  subjectMode: "uniform" | "per_course";
  uniformDurationMinutes?: number;
  uniformFeeList?: { cardProductId: number; deductAmount?: number | null }[];
  courses?: {
    id?: number;
    name: string;
    durationMinutes: number;
    feeList?: { cardProductId: number; deductAmount?: number | null }[];
  }[];
}

export interface CoachPrivateProfilePayload {
  coachStaffId?: number;
  tagText?: string | null;
  experience?: string | null;
  specialty?: string | null;
  bookingWindows?: CoachBookingWindow[];
  subjectMode?: "uniform" | "per_course";
  uniformDurationMinutes?: number;
  version?: number;
}

export async function fetchPrivateCoaches(siteId: number) {
  const response = await useApiClient().request<{ items: CoachPrivateProfile[] }>(
    `/staff/sites/${siteId}/private-coaches`,
  );
  return response.data.items;
}

export async function createPrivateCoach(siteId: number, payload: CoachPrivateProfilePayload) {
  const response = await useApiClient().request<CoachPrivateProfile>(
    `/staff/sites/${siteId}/private-coaches`,
    { method: "POST", data: payload },
  );
  return response.data;
}

export async function updatePrivateCoach(siteId: number, profileId: number, payload: CoachPrivateProfilePayload) {
  const response = await useApiClient().request<CoachPrivateProfile>(
    `/staff/sites/${siteId}/private-coaches/${profileId}`,
    { method: "PATCH" as UniApp.RequestOptions["method"], data: payload },
  );
  return response.data;
}

export async function savePrivateCoachFull(siteId: number, payload: CoachPrivateSaveFullPayload) {
  const response = await useApiClient().request<CoachPrivateProfile>(
    `/staff/sites/${siteId}/private-coaches/save`,
    { method: "POST", data: payload },
  );
  return response.data;
}

// 私教时间槽（对标原版 getDrainerTimeList）：代约弹窗按日期+课目拉取可选开始时间
export interface PrivateCoachTimeSlot {
  start: string;
  startsAt: string;
  available: boolean;
  groupOverlapWarn?: boolean;
}

export async function fetchPrivateCoachTimeSlots(
  siteId: number,
  profileId: number,
  params: { date: string; courseId?: number; excludeSessionId?: number },
) {
  // 小程序 JSCore 无 URLSearchParams，手动拼接 query
  let query = `date=${encodeURIComponent(params.date)}`;
  if (params.courseId) query += `&courseId=${params.courseId}`;
  if (params.excludeSessionId) query += `&excludeSessionId=${params.excludeSessionId}`;
  const response = await useApiClient().request<{
    date: string;
    durationMinutes: number;
    slotIntervalMinutes?: number;
    grayOutBookedSlots?: boolean;
    slots: PrivateCoachTimeSlot[];
  }>(`/staff/sites/${siteId}/private-coaches/${profileId}/time-slots?${query}`);
  return response.data;
}

// 员工代约私教（预约时间制：动态生成 private session）
export async function bookPrivateCoach(
  siteId: number,
  profileId: number,
  payload: {
    memberId: number;
    memberCardId: number;
    date: string;
    start: string;
    courseId?: number;
    remark?: string;
    commandKey: string;
    acknowledgeGroupOverlap?: boolean;
  },
) {
  const response = await useApiClient().request<{ appointment: { id: number }; sessionId: number }>(
    `/staff/sites/${siteId}/private-coaches/${profileId}/book`,
    { method: "POST", data: payload },
  );
  return response.data;
}

export async function deletePrivateCoach(siteId: number, profileId: number) {
  const response = await useApiClient().request<{ deleted: boolean }>(
    `/staff/sites/${siteId}/private-coaches/${profileId}`,
    { method: "DELETE" as UniApp.RequestOptions["method"] },
  );
  return response.data;
}

// 课程分类标签库（原版 tag-popup：选择/添加）
export interface CourseTagItem {
  key: string;
  label: string;
  color?: string | null;
}

export async function fetchCourseTags(siteId: number) {
  const response = await useApiClient().request<{ tags: CourseTagItem[] }>(
    `/staff/sites/${siteId}/course-tags`,
  );
  return response.data;
}

export async function updateCourseTags(siteId: number, tags: CourseTagItem[]) {
  const response = await useApiClient().request<{ tags: CourseTagItem[] }>(
    `/staff/sites/${siteId}/course-tags`,
    { method: "PUT", data: { tags } },
  );
  return response.data;
}
