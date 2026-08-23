import { computed, ref } from "vue";
import { defineStore } from "pinia";

import { apiRequest } from "@/api/client";

interface AdminProfile {
  id: number;
  username: string;
  name: string;
  email: string | null;
  role: "platform_super_admin";
  permissions: string[];
  lastLoginAt: string | null;
}

interface LoginData {
  accessToken: string;
  tokenType: "Bearer";
  expiresAt: string;
  admin: AdminProfile;
}

const TOKEN_KEY = "songguo-admin-token";
const PROFILE_KEY = "songguo-admin-profile";

function readToken() {
  return sessionStorage.getItem(TOKEN_KEY) ?? localStorage.getItem(TOKEN_KEY);
}

function readProfile(): AdminProfile | null {
  const source = sessionStorage.getItem(PROFILE_KEY) ?? localStorage.getItem(PROFILE_KEY);
  if (!source) return null;
  try {
    return JSON.parse(source) as AdminProfile;
  } catch {
    return null;
  }
}

export const useSessionStore = defineStore("admin-session", () => {
  const profile = ref<AdminProfile | null>(readProfile());
  const authenticated = ref(Boolean(readToken()));
  const permissions = computed(() => profile.value?.permissions ?? []);
  const displayName = computed(() => profile.value?.name ?? "超级管理员");

  function can(permission?: string) {
    return !permission || permissions.value.includes("platform:*") || permissions.value.includes(permission);
  }

  async function login(loginName: string, password: string, remember: boolean) {
    const response = await apiRequest<LoginData>("/admin/auth/login", {
      method: "POST",
      body: JSON.stringify({ login: loginName, password, deviceName: "admin-web" }),
    });
    const storage = remember ? localStorage : sessionStorage;
    const otherStorage = remember ? sessionStorage : localStorage;
    otherStorage.removeItem(TOKEN_KEY);
    otherStorage.removeItem(PROFILE_KEY);
    storage.setItem(TOKEN_KEY, response.data.accessToken);
    storage.setItem(PROFILE_KEY, JSON.stringify(response.data.admin));
    profile.value = response.data.admin;
    authenticated.value = true;
  }

  async function hydrate() {
    if (!readToken()) return false;
    try {
      const response = await apiRequest<AdminProfile>("/admin/me");
      profile.value = response.data;
      authenticated.value = true;
      return true;
    } catch {
      clearSession();
      return false;
    }
  }

  function clearSession() {
    for (const storage of [sessionStorage, localStorage]) {
      storage.removeItem(TOKEN_KEY);
      storage.removeItem(PROFILE_KEY);
    }
    profile.value = null;
    authenticated.value = false;
  }

  async function logout() {
    try {
      await apiRequest<{ loggedOut: boolean }>("/admin/auth/logout", { method: "POST" });
    } finally {
      clearSession();
    }
  }

  return { authenticated, profile, displayName, permissions, can, login, hydrate, logout };
});
