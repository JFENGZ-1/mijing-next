import { computed, ref } from "vue";
import { defineStore } from "pinia";

import { apiRequest } from "@/api/client";

interface Paginated<T> {
  items: T[];
  pagination: { page: number; perPage: number; total: number; lastPage: number };
}

export interface TenantScopeOption {
  id: number;
  name: string;
  code: string;
  status: string;
}

export interface SiteScopeOption {
  id: number;
  tenantId: number;
  name: string;
  code: string;
  status: string;
  timezone: string | null;
}

const TENANT_STORAGE_KEY = "mijing-admin-tenant-id";
const SITE_STORAGE_KEY = "mijing-admin-site-id";

function storedId(key: string) {
  const value = Number(localStorage.getItem(key));
  return Number.isInteger(value) && value > 0 ? value : null;
}

export const useBusinessScopeStore = defineStore("business-scope", () => {
  const tenants = ref<TenantScopeOption[]>([]);
  const sites = ref<SiteScopeOption[]>([]);
  const tenantId = ref<number | null>(storedId(TENANT_STORAGE_KEY));
  const siteId = ref<number | null>(storedId(SITE_STORAGE_KEY));
  const loadingTenants = ref(false);
  const loadingSites = ref(false);
  let tenantsRequestId = 0;
  let sitesRequestId = 0;

  const tenant = computed(() => tenants.value.find((item) => item.id === tenantId.value) ?? null);
  const site = computed(() => sites.value.find((item) => item.id === siteId.value) ?? null);
  const ready = computed(() => (
    tenant.value !== null
    && site.value !== null
    && site.value.tenantId === tenantId.value
  ));
  const scopeLabel = computed(() => ready.value ? `${tenant.value!.name} / ${site.value!.name}` : "请选择租户与场馆");

  async function loadTenants() {
    const requestId = ++tenantsRequestId;
    loadingTenants.value = true;
    try {
      const response = await apiRequest<Paginated<TenantScopeOption>>("/admin/tenants?perPage=100");

      if (requestId !== tenantsRequestId) return;

      tenants.value = response.data.items;
      if (tenantId.value && !tenants.value.some((item) => item.id === tenantId.value)) {
        await selectTenant(null);
      } else if (tenantId.value) {
        await loadSites();
      }
    } catch (error) {
      if (requestId === tenantsRequestId) throw error;
    } finally {
      if (requestId === tenantsRequestId) loadingTenants.value = false;
    }
  }

  async function loadSites() {
    const requestId = ++sitesRequestId;
    const requestedTenantId = tenantId.value;
    sites.value = [];
    if (!requestedTenantId) {
      loadingSites.value = false;
      return;
    }
    loadingSites.value = true;
    try {
      const response = await apiRequest<Paginated<SiteScopeOption>>(
        `/admin/tenants/${requestedTenantId}/sites?perPage=100`,
      );

      if (requestId !== sitesRequestId || tenantId.value !== requestedTenantId) return;

      sites.value = response.data.items.filter((item) => item.tenantId === requestedTenantId);
      if (siteId.value && !sites.value.some((item) => (
        item.id === siteId.value && item.tenantId === requestedTenantId
      ))) {
        selectSite(null);
      }
    } catch (error) {
      if (requestId === sitesRequestId && tenantId.value === requestedTenantId) throw error;
    } finally {
      if (requestId === sitesRequestId && tenantId.value === requestedTenantId) {
        loadingSites.value = false;
      }
    }
  }

  async function selectTenant(value: number | null) {
    tenantId.value = value;
    siteId.value = null;
    sites.value = [];
    if (value) localStorage.setItem(TENANT_STORAGE_KEY, String(value));
    else localStorage.removeItem(TENANT_STORAGE_KEY);
    localStorage.removeItem(SITE_STORAGE_KEY);
    await loadSites();
  }

  function selectSite(value: number | null) {
    const selectedSite = value === null
      ? null
      : sites.value.find((item) => item.id === value && item.tenantId === tenantId.value) ?? null;

    siteId.value = selectedSite?.id ?? null;
    if (selectedSite) localStorage.setItem(SITE_STORAGE_KEY, String(selectedSite.id));
    else localStorage.removeItem(SITE_STORAGE_KEY);
  }

  function path(suffix = "") {
    if (!ready.value) throw new Error("请先选择租户和场馆");
    const normalized = suffix.startsWith("/") || suffix === "" ? suffix : `/${suffix}`;
    return `/admin/tenants/${tenantId.value}/sites/${siteId.value}${normalized}`;
  }

  return {
    tenants,
    sites,
    tenantId,
    siteId,
    tenant,
    site,
    ready,
    scopeLabel,
    loadingTenants,
    loadingSites,
    loadTenants,
    loadSites,
    selectTenant,
    selectSite,
    path,
  };
});
