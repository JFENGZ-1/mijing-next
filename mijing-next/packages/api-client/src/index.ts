import type { ApiEnvelope, ApiErrorPayload } from "@mijing/domain-types";

export interface ApiClientOptions {
  baseUrl: string;
  getAccessToken: () => string | undefined;
  onUnauthorized?: () => void;
}

export class ApiError extends Error {
  constructor(
    public readonly statusCode: number,
    public readonly payload: ApiErrorPayload,
  ) {
    super(payload.message);
  }
}

export function createApiClient(options: ApiClientOptions) {
  type RequestOptions = Omit<UniApp.RequestOptions, "url">;

  async function request<T>(path: string, requestOptions: RequestOptions = {}) {
    const token = options.getAccessToken();
    const response = await uni.request({
      ...requestOptions,
      url: `${options.baseUrl}${path}`,
      header: {
        Accept: "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...requestOptions.header,
      },
    });

    if (response.statusCode === 401) {
      options.onUnauthorized?.();
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw new ApiError(response.statusCode, response.data as ApiErrorPayload);
    }

    return response.data as ApiEnvelope<T>;
  }

  return { request };
}
