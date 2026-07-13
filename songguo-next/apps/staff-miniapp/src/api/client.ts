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
