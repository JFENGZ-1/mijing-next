import type { ReportCourseSummary, ReportFinanceProfitSummary } from "@/types/reports";

export interface ChainSiteListItem {
  id: number;
  name: string;
  code: string;
  status: "active" | "disabled";
  accessible: boolean;
  address?: string | null;
  phone?: string | null;
}

export interface ChainBrand {
  name: string | null;
  logoUrl: string | null;
  chainActivated: boolean;
  siteCount: number;
}

export interface ChainSites {
  brand: ChainBrand;
  sites: ChainSiteListItem[];
  asOf: string;
}

export interface ChainStoreCourseItem {
  cardProductId: number;
  name: string;
  homeSiteId: number;
  linkedSiteIds: number[];
  courseScopeCount: number;
  sites: Array<{ siteId: number; siteName: string; editRoute: string }>;
}

export interface ChainStaffDirectoryItem {
  id: number;
  displayName: string;
  employeeNo: string;
  status: "active" | "departed";
  roleName: string | null;
  siteCount: number;
  hasTenantWideRole: boolean;
}

export interface ChainMembersBySite {
  siteId: number;
  memberCount: number;
}

export interface ChainMembersSummary {
  siteIds: number[];
  totalMemberCount: number;
  monthNewMemberCount: number;
  bySite: ChainMembersBySite[];
  asOf: string;
}

export interface ChainFinanceSummary extends ReportFinanceProfitSummary {
  siteIds: number[];
}

export interface ChainCourseSummary extends ReportCourseSummary {
  siteIds: number[];
}
