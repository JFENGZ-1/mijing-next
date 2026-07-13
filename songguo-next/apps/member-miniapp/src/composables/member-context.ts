import { useApiClient } from "@/api/client";
import { useSessionStore } from "@/stores/session";
import type { MemberSiteOption } from "@/types/member";

export interface MemberContext {
  tenantId: number;
  siteId: number;
  siteName: string;
}

let joinableSitesCache: MemberSiteOption[] | undefined;
let joinedSitesCache: MemberSiteOption[] | undefined;

/** Active sites available for explicit membership joining (onboarding). */
export async function loadJoinableSites() {
  if (!joinableSitesCache) {
    const response = await useApiClient().request<MemberSiteOption[]>("/member/sites");
    joinableSitesCache = response.data;
  }
  return joinableSitesCache;
}

/** Sites the current account has already joined and may switch to. */
export async function loadJoinedMemberSites() {
  if (!joinedSitesCache) {
    const response = await useApiClient().request<MemberSiteOption[]>("/member/memberships");
    joinedSitesCache = response.data;
  }
  return joinedSitesCache;
}

/** @deprecated Use loadJoinableSites or loadJoinedMemberSites explicitly. */
export const loadMemberSites = loadJoinableSites;

export function clearMemberSitesCache() {
  joinableSitesCache = undefined;
  joinedSitesCache = undefined;
}

function findJoinedSite(
  sites: MemberSiteOption[],
  tenantId: number | undefined,
  siteId: number | undefined,
) {
  if (!siteId) return undefined;
  if (tenantId) {
    return sites.find((item) => item.id === siteId && item.tenantId === tenantId);
  }
  return sites.find((item) => item.id === siteId);
}

export async function ensureMemberContext(): Promise<MemberContext | null> {
  const session = useSessionStore();
  session.hydrate();

  const joinedSites = await loadJoinedMemberSites();
  const matched = findJoinedSite(joinedSites, session.currentTenantId, session.currentSiteId);
  if (matched) {
    if (session.currentTenantId !== matched.tenantId || session.currentSiteId !== matched.id) {
      session.setSiteContext(matched.tenantId, matched.id);
    }
    return {
      tenantId: matched.tenantId,
      siteId: matched.id,
      siteName: matched.name,
    };
  }

  if (joinedSites.length === 1) {
    const site = joinedSites[0];
    session.setSiteContext(site.tenantId, site.id);
    return { tenantId: site.tenantId, siteId: site.id, siteName: site.name };
  }

  return null;
}

export async function ensureMemberTenant(): Promise<{ tenantId: number } | null> {
  const context = await ensureMemberContext();
  if (context) return { tenantId: context.tenantId };

  const session = useSessionStore();
  session.hydrate();
  if (session.currentTenantId) {
    const joinedSites = await loadJoinedMemberSites();
    if (joinedSites.some((site) => site.tenantId === session.currentTenantId)) {
      return { tenantId: session.currentTenantId };
    }
  }

  return null;
}

export function selectMemberSite(site: MemberSiteOption) {
  const session = useSessionStore();
  session.setSiteContext(site.tenantId, site.id);
  clearMemberSitesCache();
}
