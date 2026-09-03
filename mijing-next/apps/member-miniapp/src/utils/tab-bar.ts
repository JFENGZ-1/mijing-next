interface CustomTabBarInstance {
  setData(data: { selected: number }): void;
}

interface PageWithCustomTabBar {
  getTabBar?: () => CustomTabBarInstance | undefined;
}

export function syncMemberTabBar(selected: number) {
  // #ifdef MP-WEIXIN
  const applySelection = () => {
    const pages = getCurrentPages();
    const page = pages[pages.length - 1] as unknown as PageWithCustomTabBar | undefined;
    page?.getTabBar?.()?.setData({ selected });
  };

  applySelection();
  setTimeout(applySelection, 0);
  // #endif
}
