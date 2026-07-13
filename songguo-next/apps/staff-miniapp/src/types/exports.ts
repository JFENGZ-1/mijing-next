import type { CrmFilterPresetQuery } from "@/types/crm";

export type ExportJobType = "member_export" | "card_export";
export type ExportJobStatus = "pending" | "processing" | "completed" | "failed";

export interface ExportJob {
  id: number;
  type: ExportJobType;
  status: ExportJobStatus;
  requestedByStaffId: number;
  requestedByStaffName: string | null;
  filters: Record<string, unknown>;
  createdAt: string | null;
  completedAt: string | null;
  downloadAvailable: boolean;
}

export interface ExportJobList {
  items: ExportJob[];
  pagination: {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
  };
}

export type MemberExportCreatePayload = CrmFilterPresetQuery & {
  status?: "lead" | "active" | "frozen" | "closed";
  q?: string;
  includeVisitors?: boolean;
  tagIds?: string;
  pinyinInitial?: string;
  columns?: string[];
};
