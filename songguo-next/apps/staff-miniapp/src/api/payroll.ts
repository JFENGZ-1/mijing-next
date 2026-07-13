import { useApiClient } from "@/api/client";
import type {
  PayrollCoachConfig,
  PayrollCoachList,
  PayrollCoachReportList,
  PayrollCoachRules,
  PayrollCoachRulesInput,
  PayrollRecomputeJob,
  PayrollRecomputeJobInput,
  PayrollRecomputeJobList,
  PayrollSalesConfig,
  PayrollSalesReportList,
} from "@/types/payroll";

function payrollPath(siteId: number, suffix: string) {
  return `/staff/sites/${siteId}/payroll${suffix}`;
}

export async function fetchPayrollCoachReports(siteId: number, year: number, month: number) {
  const response = await useApiClient().request<PayrollCoachReportList>(
    `${payrollPath(siteId, "/coach-reports")}?year=${year}&month=${month}`,
  );
  return response.data;
}

export async function fetchPayrollSalesReports(siteId: number, year: number, month: number) {
  const response = await useApiClient().request<PayrollSalesReportList>(
    `${payrollPath(siteId, "/sales-reports")}?year=${year}&month=${month}`,
  );
  return response.data;
}

export async function createRecomputeJob(siteId: number, payload: PayrollRecomputeJobInput) {
  const response = await useApiClient().request<PayrollRecomputeJob>(payrollPath(siteId, "/recompute-jobs"), {
    method: "POST",
    data: payload,
    header: { "Content-Type": "application/json" },
  });
  return response.data;
}

export async function listRecomputeJobs(siteId: number, page = 1, perPage = 20) {
  const response = await useApiClient().request<PayrollRecomputeJobList>(
    `${payrollPath(siteId, "/recompute-jobs")}?page=${page}&perPage=${perPage}`,
  );
  return response.data;
}

export async function fetchCoachConfig(siteId: number) {
  const response = await useApiClient().request<PayrollCoachConfig>(payrollPath(siteId, "/coach-config"));
  return response.data;
}

export async function updateCoachConfig(siteId: number, payload: PayrollCoachConfig) {
  const response = await useApiClient().request<PayrollCoachConfig>(payrollPath(siteId, "/coach-config"), {
    method: "PUT",
    data: payload,
    header: { "Content-Type": "application/json" },
  });
  return response.data;
}

export async function fetchSalesConfig(siteId: number) {
  const response = await useApiClient().request<PayrollSalesConfig>(payrollPath(siteId, "/sales-config"));
  return response.data;
}

export async function updateSalesConfig(siteId: number, payload: PayrollSalesConfig) {
  const response = await useApiClient().request<PayrollSalesConfig>(payrollPath(siteId, "/sales-config"), {
    method: "PUT",
    data: payload,
    header: { "Content-Type": "application/json" },
  });
  return response.data;
}

export async function listPayrollCoaches(siteId: number) {
  const response = await useApiClient().request<PayrollCoachList>(payrollPath(siteId, "/coaches"));
  return response.data;
}

export async function fetchCoachRules(siteId: number, staffId: number) {
  const response = await useApiClient().request<PayrollCoachRules>(
    `${payrollPath(siteId, "/coach-rules")}?staffId=${staffId}`,
  );
  return response.data;
}

export async function updateCoachRules(siteId: number, staffId: number, payload: PayrollCoachRulesInput) {
  const response = await useApiClient().request<PayrollCoachRules>(
    `${payrollPath(siteId, "/coach-rules")}?staffId=${staffId}`,
    {
      method: "PUT",
      data: payload,
      header: { "Content-Type": "application/json" },
    },
  );
  return response.data;
}
