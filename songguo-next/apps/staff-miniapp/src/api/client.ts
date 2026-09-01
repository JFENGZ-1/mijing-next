import { createApiClient } from "@songguo/api-client";
import { useSessionStore } from "@/stores/session";
import { redirectToLogin } from "@/auth/navigation";

export function useApiClient() {
  const session = useSessionStore();
  return createApiClient({
    baseUrl: import.meta.env.VITE_API_BASE_URL,
    getAccessToken: () => session.accessToken || undefined,
    onUnauthorized: redirectToLogin,
  });
}

/**
 * Public endpoints (such as WeChat login) must surface their own 401 errors.
 * Redirecting on those responses would recreate the login page before its
 * error state can be rendered, making the button look unresponsive.
 */
export function usePublicApiClient() {
  return createApiClient({
    baseUrl: import.meta.env.VITE_API_BASE_URL,
    getAccessToken: () => undefined,
  });
}
