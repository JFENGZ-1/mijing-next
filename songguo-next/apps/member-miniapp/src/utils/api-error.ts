import { ApiError } from "@songguo/api-client";

export function formatApiErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    if (error.payload.message) return error.payload.message;
    if (error.payload.code) return error.payload.code;
  }
  if (error instanceof Error && error.message) return error.message;
  return fallback;
}
