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
</style>
