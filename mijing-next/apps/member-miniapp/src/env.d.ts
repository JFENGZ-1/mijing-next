/// <reference types="vite/client" />

declare module "uview-plus" {
  import type { App } from "vue";
  const plugin: { install(app: App): void };
  export default plugin;
}

interface UviewToastOptions {
  message?: string;
  title?: string;
  type?: "default" | "success" | "error" | "warning";
  duration?: number;
}

interface Uni {
  $u: {
    toast(options: string | UviewToastOptions): void;
  };
}

interface ImportMetaEnv {
  readonly VITE_API_BASE_URL: string;
}

declare module '*.vue' {
  import { DefineComponent } from 'vue'
  // eslint-disable-next-line @typescript-eslint/no-explicit-any, @typescript-eslint/ban-types
  const component: DefineComponent<{}, {}, any>
  export default component
}
