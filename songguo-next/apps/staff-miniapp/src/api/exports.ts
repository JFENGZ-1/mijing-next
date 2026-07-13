import { useApiClient } from "@/api/client";
import { useSessionStore } from "@/stores/session";
import type { ExportJob, ExportJobList, MemberExportCreatePayload } from "@/types/exports";

function exportsPath(siteId: number, suffix: string) {
  return `/staff/sites/${siteId}/exports${suffix}`;
}

export async function createMemberExport(siteId: number, payload: MemberExportCreatePayload = {}) {
  const response = await useApiClient().request<ExportJob>(exportsPath(siteId, "/members"), {
    method: "POST",
    data: payload,
    header: { "Content-Type": "application/json" },
  });
  return response.data;
}

export async function listExportJobs(siteId: number, page = 1, perPage = 20) {
  const response = await useApiClient().request<ExportJobList>(
    `${exportsPath(siteId, "/jobs")}?page=${page}&perPage=${perPage}`,
  );
  return response.data;
}

export function downloadExportJobUrl(siteId: number, jobId: number): string {
  return `${import.meta.env.VITE_API_BASE_URL}${exportsPath(siteId, `/jobs/${jobId}/download`)}`;
}

export async function downloadExportJob(siteId: number, jobId: number): Promise<string> {
  const session = useSessionStore();
  const token = session.accessToken;
  if (!token) throw new Error("未登录");

  const downloadResult = await uni.downloadFile({
    url: downloadExportJobUrl(siteId, jobId),
    header: { Authorization: `Bearer ${token}` },
  });

  if (downloadResult.statusCode !== 200) {
    throw new Error("导出文件下载失败");
  }

  return downloadResult.tempFilePath;
}
