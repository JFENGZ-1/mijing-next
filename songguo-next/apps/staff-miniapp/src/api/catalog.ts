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
