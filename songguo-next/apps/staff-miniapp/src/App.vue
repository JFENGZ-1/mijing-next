<script setup lang="ts">
import { onLaunch, onShow, onHide } from "@dcloudio/uni-app";
import { useSessionStore } from "@/stores/session";
onLaunch(() => {
  useSessionStore().hydrate();
});
onShow(() => {
  console.log("App Show");
});
onHide(() => {
  console.log("App Hide");
});
</script>
<style lang="scss">
@import "uview-plus/index.scss";
@import "@/styles/common.scss";

/* uview-plus 运行时 CSS 变量覆盖（对标原版橙色主题） */
page {
  --up-primary: #ed920f;
  --up-primary-dark: #f88302;
  --up-primary-disabled: #f7cf9a;
  --up-primary-light: #fdf3e3;
  --up-success: #22c788;
  --up-warning: #f88302;
  --up-error: #dc3c5c;
  --up-info: #5fa3ea;
  --up-main-color: #181818;
  --up-content-color: #505050;
  --up-tips-color: #989898;
  --up-light-color: #bfbfbf;
}
</style>
