import { useApiClient } from "@/api/client";
import type { StaffProfile, StaffProfileAvatar } from "@/types/profile";

export async function fetchStaffProfile() {
  const response = await useApiClient().request<StaffProfile>("/staff/profile");
  return response.data;
}

export async function updateStaffProfile(payload: { displayName?: string; avatarUrl?: string | null; version?: number }) {
  const response = await useApiClient().request<StaffProfile>("/staff/profile", {
    method: "PATCH" as UniApp.RequestOptions["method"],
    data: payload,
  });
  return response.data;
}

export function uploadStaffAvatar(filePath: string) {
  const session = uni.getStorageSync("access_token") as string;
  const baseUrl = import.meta.env.VITE_API_BASE_URL as string;

  return new Promise<StaffProfileAvatar>((resolve, reject) => {
    uni.uploadFile({
      url: `${baseUrl}/staff/profile/avatar`,
      filePath,
      name: "avatar",
      header: session ? { Authorization: `Bearer ${session}` } : {},
      success: (result) => {
        try {
          const payload = JSON.parse(result.data) as { data?: StaffProfileAvatar; message?: string };
          if (result.statusCode >= 400 || !payload.data) {
            reject(new Error(payload.message || "头像上传失败"));
            return;
          }
          resolve(payload.data);
        } catch {
          reject(new Error("头像上传失败"));
        }
      },
      fail: () => reject(new Error("头像上传失败")),
    });
  });
}
