import { defineStore } from "pinia";

export interface StaffSiteContext {
  id: number;
  name: string;
  status: string;
  permissions: string[];
}

export const useSessionStore = defineStore("session", {
  state: () => ({
    accessToken: "" as string,
    currentTenantId: undefined as number | undefined,
    currentSiteId: undefined as number | undefined,
    sites: [] as StaffSiteContext[],
    permissions: [] as string[],
    hydrated: false,
    validated: false,
  }),
  actions: {
    hydrate() {
      if (this.hydrated) return;
      this.accessToken = uni.getStorageSync("access_token") || "";
      this.currentTenantId = uni.getStorageSync("current_tenant_id") || undefined;
      this.currentSiteId = uni.getStorageSync("current_site_id") || undefined;
      this.sites = uni.getStorageSync("staff_sites") || [];
      this.permissions = this.sites.find((site) => site.id === this.currentSiteId)?.permissions
        || uni.getStorageSync("permissions") || [];
      this.hydrated = true;
    },
    setSession(payload: { accessToken: string; tenantId: number; permissions: string[]; sites: StaffSiteContext[] }) {
      this.accessToken = payload.accessToken;
      this.currentTenantId = payload.tenantId;
      this.sites = payload.sites;
      this.currentSiteId = payload.sites[0]?.id;
      this.permissions = payload.sites[0]?.permissions || payload.permissions;
      this.hydrated = true;
      this.validated = true;
      uni.setStorageSync("access_token", payload.accessToken);
      uni.setStorageSync("current_tenant_id", payload.tenantId);
      uni.setStorageSync("permissions", payload.permissions);
      uni.setStorageSync("staff_sites", payload.sites);
      if (this.currentSiteId) uni.setStorageSync("current_site_id", this.currentSiteId);
    },
    setVerifiedContext(tenantId: number, permissions: string[], sites: StaffSiteContext[]) {
      this.currentTenantId = tenantId;
      this.sites = sites;
      if (!this.currentSiteId || !sites.some((site) => site.id === this.currentSiteId)) {
        this.currentSiteId = sites[0]?.id;
      }
      this.permissions = sites.find((site) => site.id === this.currentSiteId)?.permissions || permissions;
      this.validated = true;
      uni.setStorageSync("current_tenant_id", tenantId);
      uni.setStorageSync("permissions", permissions);
      uni.setStorageSync("staff_sites", sites);
      if (this.currentSiteId) uni.setStorageSync("current_site_id", this.currentSiteId);
    },
    can(permission: string) {
      return this.permissions.includes(permission);
    },
    selectSite(siteId: number) {
      const site = this.sites.find((item) => item.id === siteId);
      if (!site) return false;
      this.currentSiteId = site.id;
      this.permissions = site.permissions;
      uni.setStorageSync("current_site_id", site.id);
      return true;
    },
    clear() {
      this.$reset();
      uni.removeStorageSync("access_token");
      uni.removeStorageSync("current_tenant_id");
      uni.removeStorageSync("current_site_id");
      uni.removeStorageSync("permissions");
      uni.removeStorageSync("staff_sites");
    },
  },
});
