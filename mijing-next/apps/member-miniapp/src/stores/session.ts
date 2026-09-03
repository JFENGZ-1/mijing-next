import { defineStore } from "pinia";
import type { RegistrationState } from "@/types/member";

export const useSessionStore = defineStore("session", {
  state: () => ({
    accessToken: "" as string,
    currentTenantId: undefined as number | undefined,
    currentSiteId: undefined as number | undefined,
    hydrated: false,
    validated: false,
    registrationState: undefined as RegistrationState | undefined,
  }),
  actions: {
    hydrate() {
      if (this.hydrated) return;
      this.accessToken = uni.getStorageSync("access_token") || "";
      this.currentTenantId = uni.getStorageSync("current_tenant_id") || undefined;
      this.currentSiteId = uni.getStorageSync("current_site_id") || undefined;
      this.hydrated = true;
    },
    setSiteContext(tenantId: number, siteId: number) {
      this.currentTenantId = tenantId;
      this.currentSiteId = siteId;
      uni.setStorageSync("current_tenant_id", tenantId);
      uni.setStorageSync("current_site_id", siteId);
    },
    setToken(token: string, registrationState?: RegistrationState) {
      this.accessToken = token;
      this.hydrated = true;
      this.validated = true;
      this.registrationState = registrationState;
      uni.setStorageSync("access_token", token);
    },
    markValidated(registrationState?: RegistrationState) {
      this.validated = true;
      this.registrationState = registrationState;
    },
    setRegistrationState(registrationState: RegistrationState) {
      this.registrationState = registrationState;
    },
    clear() {
      this.$reset();
      uni.removeStorageSync("access_token");
      uni.removeStorageSync("current_tenant_id");
      uni.removeStorageSync("current_site_id");
    },
  },
});
