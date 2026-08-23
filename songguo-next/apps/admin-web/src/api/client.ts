export interface ApiEnvelope<T> {
  data: T;
  requestId: string;
}

export interface ApiErrorPayload {
  code: string;
  message: string;
  requestId?: string;
  errors?: Record<string, string[]>;
}

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly payload: ApiErrorPayload,
  ) {
    super(payload.message);
  }
}

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || "/api/v1";

function csrfToken() {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : undefined;
}

export async function apiRequest<T>(path: string, init: RequestInit = {}) {
  const token = sessionStorage.getItem("songguo-admin-token") ?? localStorage.getItem("songguo-admin-token");
  const isFormData = init.body instanceof FormData;
  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...init,
    credentials: "include",
    headers: {
      Accept: "application/json",
      ...(!isFormData ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(csrfToken() ? { "X-XSRF-TOKEN": csrfToken()! } : {}),
      ...init.headers,
    },
  });

  const payload = (await response.json().catch(() => ({
    code: "INVALID_RESPONSE",
    message: "服务返回了无法解析的响应",
  }))) as ApiEnvelope<T> | ApiErrorPayload;

  if (!response.ok) {
    throw new ApiError(response.status, payload as ApiErrorPayload);
  }

  return payload as ApiEnvelope<T>;
}

export async function apiBlob(path: string) {
  const token = sessionStorage.getItem("songguo-admin-token") ?? localStorage.getItem("songguo-admin-token");
  const response = await fetch(`${API_BASE_URL}${path}`, {
    credentials: "include",
    headers: {
      Accept: "*/*",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
  if (!response.ok) throw new Error(`资源读取失败：${response.status}`);
  return response.blob();
}
