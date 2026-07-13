import { useSessionStore } from "@/stores/session";

let redirecting = false;
let onboardingRedirecting = false;

export function redirectToLogin() {
  const session = useSessionStore();
  session.clear();
  if (redirecting) return;

  redirecting = true;
  uni.reLaunch({
    url: "/pages/login/index",
    complete: () => {
      redirecting = false;
    },
  });
}

export function redirectToOnboarding() {
  if (onboardingRedirecting) return;
  onboardingRedirecting = true;
  uni.reLaunch({
    url: "/pages/onboarding/profile",
    complete: () => {
      onboardingRedirecting = false;
    },
  });
}
