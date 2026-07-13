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

page {
  --up-primary: #{$color-primary};
  --u-primary: #{$color-primary};
  --up-primary-dark: #{$color-primary-dark};
  --u-primary-dark: #{$color-primary-dark};
  --up-primary-light: #{$color-primary-light};
  --u-primary-light: #{$color-primary-light};
}
</style>
