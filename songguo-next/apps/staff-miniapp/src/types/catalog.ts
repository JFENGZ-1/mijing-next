import type { CourseType } from "@/types/scheduling";

export type CourseCatalogStatus = "active" | "archived";

export interface CourseCatalogItem {
  id: number;
  courseType: CourseType;
  name: string;
  durationMinutes: number;
  difficulty?: number | null;
  minCapacity?: number | null;
  maxCapacity?: number | null;
  defaultRoomId?: number | null;
  defaultRoomName?: string | null;
  coachStaffId?: number | null;
  coachName?: string | null;
  catalogStatus?: CourseCatalogStatus;
  sortOrder?: number;
  version?: number;
}

export interface CourseCatalogList {
  items: CourseCatalogItem[];
  pagination: {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
  };
}

export interface CourseDetail extends CourseCatalogItem {
  description?: string | null;
  tags: string[];
  archivedAt?: string | null;
  createdAt?: string | null;
  updatedAt?: string | null;
}

export interface CourseUpsertPayload {
  courseType: CourseType;
  name: string;
  durationMinutes: number;
  description?: string | null;
  difficulty?: number | null;
  minCapacity?: number | null;
  maxCapacity?: number | null;
  defaultRoomId?: number | null;
  coachStaffId?: number | null;
  tags?: string[];
  sortOrder?: number;
}

export interface CourseUpdatePayload extends CourseUpsertPayload {
  version: number;
}

export type RoomCatalogStatus = "active" | "archived";

export interface RoomCatalogItem {
  id: number;
  name: string;
  capacity?: number | null;
  catalogStatus?: RoomCatalogStatus;
  sortOrder?: number;
  version?: number;
  archivedAt?: string | null;
  createdAt?: string | null;
  updatedAt?: string | null;
}

export interface RoomUpsertPayload {
  name: string;
  capacity?: number | null;
  sortOrder?: number;
}

export interface RoomUpdatePayload extends RoomUpsertPayload {
  version: number;
}

export interface RoomCatalogList {
  items: RoomCatalogItem[];
}
