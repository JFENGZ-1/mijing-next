import { useSessionStore } from "@/stores/session";

let redirecting = false;

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
