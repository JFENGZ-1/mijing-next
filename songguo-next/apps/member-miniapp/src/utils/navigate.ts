let lastNavigateAt = 0;

/**
 * 防抖导航：短时间内重复触发只入栈一次。
 * 用于修复"外层 @tap 与内部按钮 @click 双触发"以及用户快速双击导致的重复入栈
 * （表现为详情页需要按两次返回才能回到上一页）。
 */
export function navigateToOnce(url: string) {
  const now = Date.now();
  if (now - lastNavigateAt < 500) return;
  lastNavigateAt = now;
  uni.navigateTo({ url });
}
