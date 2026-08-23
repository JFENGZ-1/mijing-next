import { useApiClient } from "@/api/client";
import type {
  ScheduleBatchCancelInput,
  ScheduleBatchCancelResult,
  ScheduleBatchCopyInput,
  ScheduleBatchCopyResult,
  ScheduleBatchSuspendInput,
  ScheduleBatchSuspendResult,
  ScheduleChangeCoursePreflight,
  ScheduleDisplayConfig,
  ScheduleSession,
  ScheduleSessionColorPalette,
  ScheduleSessionCreateInput,
  ScheduleSessionUpdateInput,
  StaffAppointment,
  StaffAppointmentList,
  StaffBookingDailyBoard,
  StaffCancelAppointmentInput,
  StaffCreateAppointmentInput,
  StaffMarkAbsentInput,
  StaffPromoteWaitlistInput,
  StaffRescheduleAppointmentInput,
  StaffUpdateAppointmentNotesInput,
} from "@/types/scheduling";

function sitePath(siteId: number, suffix: string) {
  return `/staff/sites/${siteId}${suffix}`;
}

export async function fetchStaffBookingDailyBoard(siteId: number, date: string) {
  const response = await useApiClient().request<StaffBookingDailyBoard>(
    `${sitePath(siteId, "/booking/daily-board")}?date=${encodeURIComponent(date)}`,
  );
  return response.data;
}

export async function fetchStaffScheduleSessions(siteId: number, from: string, to: string) {
  const qs = `from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`;
  const response = await useApiClient().request<{ items: ScheduleSession[] }>(
    `${sitePath(siteId, "/schedule-sessions")}?${qs}`,
  );
  return response.data;
}

export async function fetchStaffScheduleSession(siteId: number, sessionId: number) {
  const response = await useApiClient().request<ScheduleSession>(
    sitePath(siteId, `/schedule-sessions/${sessionId}`),
  );
  return response.data;
}

export async function fetchStaffSessionWaitlist(siteId: number, sessionId: number) {
  const response = await useApiClient().request<StaffAppointmentList>(
    sitePath(siteId, `/schedule-sessions/${sessionId}/waitlist`),
  );
  return response.data;
}

export async function fetchStaffSessionAppointments(siteId: number, sessionId: number) {
  const response = await useApiClient().request<StaffAppointmentList>(
    sitePath(siteId, `/schedule-sessions/${sessionId}/appointments`),
  );
  return response.data;
}

export async function cancelStaffAppointment(
  siteId: number,
  appointmentId: number,
  payload: StaffCancelAppointmentInput,
) {
  const response = await useApiClient().request<StaffAppointment>(
    sitePath(siteId, `/appointments/${appointmentId}/cancel`),
    { method: "POST", data: payload },
  );
  return response.data;
}

export async function promoteStaffWaitlistAppointment(
  siteId: number,
  appointmentId: number,
  payload: StaffPromoteWaitlistInput,
) {
  const response = await useApiClient().request<StaffAppointment>(
    sitePath(siteId, `/appointments/${appointmentId}/promote`),
    { method: "POST", data: payload },
  );
  return response.data;
}

export async function markStaffAppointmentAbsent(
  siteId: number,
  appointmentId: number,
  payload: StaffMarkAbsentInput,
) {
  const response = await useApiClient().request<StaffAppointment>(
    sitePath(siteId, `/appointments/${appointmentId}/mark-absent`),
    { method: "POST", data: payload },
  );
  return response.data;
}

export interface StaffCheckInResolveResult {
  member: { id: number; memberNo: string; displayName: string };
  appointments: StaffAppointment[];
}

export async function resolveStaffCheckIn(siteId: number, code: string) {
  const response = await useApiClient().request<StaffCheckInResolveResult>(
    sitePath(siteId, "/check-in/resolve"),
    { method: "POST", data: { code } },
  );
  return response.data;
}

export async function markStaffAppointmentCheckIn(
  siteId: number,
  appointmentId: number,
  commandKey: string,
) {
  const response = await useApiClient().request<StaffAppointment>(
    sitePath(siteId, `/appointments/${appointmentId}/mark-check-in`),
    { method: "POST", data: { commandKey } },
  );
  return response.data;
}

export async function updateStaffAppointmentNotes(
  siteId: number,
  appointmentId: number,
  payload: StaffUpdateAppointmentNotesInput,
) {
  const response = await useApiClient().request<StaffAppointment>(
    sitePath(siteId, `/appointments/${appointmentId}/staff-notes`),
    {
      method: "PATCH" as UniApp.RequestOptions["method"],
      data: payload,
    },
  );
  return response.data;
}

export async function rescheduleStaffAppointment(
  siteId: number,
  appointmentId: number,
  payload: StaffRescheduleAppointmentInput,
) {
  const response = await useApiClient().request<StaffAppointment>(
    sitePath(siteId, `/appointments/${appointmentId}/reschedule`),
    { method: "POST", data: payload },
  );
  return response.data;
}

export async function suspendStaffScheduleSession(siteId: number, sessionId: number) {
  const response = await useApiClient().request<ScheduleSession>(
    sitePath(siteId, `/schedule-sessions/${sessionId}/suspend`),
    { method: "POST" },
  );
  return response.data;
}

export async function cancelStaffScheduleSession(siteId: number, sessionId: number) {
  const response = await useApiClient().request<ScheduleSession>(
    sitePath(siteId, `/schedule-sessions/${sessionId}/cancel`),
    { method: "POST" },
  );
  return response.data;
}

export async function unsuspendStaffScheduleSession(siteId: number, sessionId: number) {
  const response = await useApiClient().request<ScheduleSession>(
    sitePath(siteId, `/schedule-sessions/${sessionId}/unsuspend`),
    { method: "POST" },
  );
  return response.data;
}

// 换课预检（对标原版 checkchangeOtherCourse：判断是否已有会员预约）
export async function fetchChangeCoursePreflight(
  siteId: number,
  sessionIds: number[],
  targetCourseId: number,
) {
  const qs = sessionIds.map((id) => `sessionIds[]=${id}`).join("&");
  const response = await useApiClient().request<ScheduleChangeCoursePreflight>(
    `${sitePath(siteId, "/schedule-sessions/change-course-preflight")}?${qs}&targetCourseId=${targetCourseId}`,
  );
  return response.data;
}

// 课表背景色板（对标原版 getBgColor）
export async function fetchScheduleSessionColors(siteId: number) {
  const response = await useApiClient().request<ScheduleSessionColorPalette>(
    sitePath(siteId, "/schedule-session-colors"),
  );
  return response.data;
}

export async function createStaffAppointment(
  siteId: number,
  sessionId: number,
  payload: StaffCreateAppointmentInput,
) {
  const response = await useApiClient().request<StaffAppointment>(
    sitePath(siteId, `/schedule-sessions/${sessionId}/appointments`),
    { method: "POST", data: payload },
  );
  return response.data;
}

export async function createStaffScheduleSession(siteId: number, payload: ScheduleSessionCreateInput) {
  const response = await useApiClient().request<ScheduleSession>(sitePath(siteId, "/schedule-sessions"), {
    method: "POST",
    data: payload,
  });
  return response.data;
}

export async function updateStaffScheduleSession(
  siteId: number,
  sessionId: number,
  payload: ScheduleSessionUpdateInput,
) {
  const response = await useApiClient().request<ScheduleSession>(
    sitePath(siteId, `/schedule-sessions/${sessionId}`),
    {
      method: "PATCH" as UniApp.RequestOptions["method"],
      data: payload,
    },
  );
  return response.data;
}

export async function batchCopyStaffScheduleSessions(siteId: number, payload: ScheduleBatchCopyInput) {
  const response = await useApiClient().request<ScheduleBatchCopyResult>(
    sitePath(siteId, "/schedule-sessions/batch-copy"),
    { method: "POST", data: payload },
  );
  return response.data;
}

export async function batchSuspendStaffScheduleSessions(siteId: number, payload: ScheduleBatchSuspendInput) {
  const response = await useApiClient().request<ScheduleBatchSuspendResult>(
    sitePath(siteId, "/schedule-sessions/batch-suspend"),
    { method: "POST", data: payload },
  );
  return response.data;
}

export async function batchCancelStaffScheduleSessions(siteId: number, payload: ScheduleBatchCancelInput) {
  const response = await useApiClient().request<ScheduleBatchCancelResult>(
    sitePath(siteId, "/schedule-sessions/batch-cancel"),
    { method: "POST", data: payload },
  );
  return response.data;
}

// 整体换课（对标原版 batchChangeCourse）
export async function batchChangeCourseStaffScheduleSessions(
  siteId: number,
  payload: { sessionIds: number[]; targetCourseId: number; commandKey: string },
) {
  const response = await useApiClient().request<{ changedCount?: number; skipped?: unknown[] }>(
    sitePath(siteId, "/schedule-sessions/batch-change-course"),
    { method: "POST", data: payload },
  );
  return response.data;
}

export async function fetchScheduleDisplayConfig(siteId: number) {
  const response = await useApiClient().request<ScheduleDisplayConfig>(
    sitePath(siteId, "/schedule-display-config"),
  );
  return response.data;
}

export async function updateScheduleDisplayConfig(siteId: number, payload: ScheduleDisplayConfig) {
  const response = await useApiClient().request<ScheduleDisplayConfig>(
    sitePath(siteId, "/schedule-display-config"),
    { method: "PUT", data: payload },
  );
  return response.data;
}

// 下载课表图片（原版 getArrangeImage）
export async function exportScheduleImage(siteId: number, from: string, to: string) {
  const response = await useApiClient().request<{
    imageUrl: string;
    width: number;
    height: number;
    placeholder: boolean;
  }>(sitePath(siteId, "/schedule-export-image"), { method: "POST", data: { from, to } });
  return response.data;
}
