import { useApiClient } from "@/api/client";
import { redirectToLogin, redirectToOnboarding } from "@/auth/navigation";
import { useSessionStore } from "@/stores/session";
import type { MemberOnboardingData } from "@/types/member";

let validationPromise: Promise<boolean> | undefined;

export async function requireMemberAuth(options: { allowIncomplete?: boolean } = {}): Promise<boolean> {
  const session = useSessionStore();
  session.hydrate();

  if (!session.accessToken) {
    redirectToLogin();
    return false;
  }
  if (session.validated) {
    if (!options.allowIncomplete && session.registrationState !== "complete") {
      redirectToOnboarding();
      return false;
    }
    return true;
  }

  validationPromise ??= useApiClient()
    .request<{ memberRegistration: MemberOnboardingData }>("/me")
    .then((response) => {
      session.markValidated(response.data.memberRegistration.state);
      return true;
    })
    .catch(() => {
      redirectToLogin();
      return false;
    })
    .finally(() => {
      validationPromise = undefined;
    });

  const authenticated = await validationPromise;
  if (authenticated && !options.allowIncomplete && session.registrationState !== "complete") {
    redirectToOnboarding();
    return false;
  }
  return authenticated;
}
