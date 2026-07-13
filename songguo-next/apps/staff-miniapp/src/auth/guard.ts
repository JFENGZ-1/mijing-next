import { useApiClient } from "@/api/client";
import { redirectToLogin } from "@/auth/navigation";
import { useSessionStore } from "@/stores/session";
import type { StaffSiteContext } from "@/stores/session";

interface StaffProfile {
  tenantId: number;
  permissions: string[];
  sites: StaffSiteContext[];
}

let validationPromise: Promise<boolean> | undefined;

export async function requireStaffAuth(): Promise<boolean> {
  const session = useSessionStore();
  session.hydrate();

  if (!session.accessToken) {
    redirectToLogin();
    return false;
  }
  if (session.validated) return true;

  validationPromise ??= useApiClient()
    .request<{ staffProfiles: StaffProfile[] }>("/me")
    .then((response) => {
      const staff = response.data.staffProfiles[0];
      if (!staff) throw new Error("当前账号没有有效员工身份");
      session.setVerifiedContext(staff.tenantId, staff.permissions, staff.sites);
      return true;
    })
    .catch(() => {
      redirectToLogin();
      return false;
    })
    .finally(() => {
      validationPromise = undefined;
    });

  return validationPromise;
}
