const items = [
  {
    pagePath: "pages/index/index",
    text: "首页",
    iconPath: "/static/tabbar/home.png",
    selectedIconPath: "/static/tabbar/home-active.png",
  },
  {
    pagePath: "pages/booking/index",
    text: "约课",
    iconPath: "/static/tabbar/booking.png",
    selectedIconPath: "/static/tabbar/booking-active.png",
  },
  {
    pagePath: "pages/mine/index",
    text: "我的",
    iconPath: "/static/tabbar/mine.png",
    selectedIconPath: "/static/tabbar/mine-active.png",
  },
];

Component({
  data: {
    selected: 0,
    color: "#505050",
    selectedColor: "#181818",
    items,
  },
  lifetimes: {
    attached() {
      this.syncSelected();
    },
  },
  pageLifetimes: {
    show() {
      this.syncSelected();
    },
  },
  methods: {
    syncSelected() {
      const pages = getCurrentPages();
      const currentPage = pages[pages.length - 1];
      const route = currentPage && (currentPage.route || currentPage.__route__);
      const selected = items.findIndex((item) => item.pagePath === route);

      if (selected >= 0 && selected !== this.data.selected) {
        this.setData({ selected });
      }
    },
    switchTab(event) {
      const index = Number(event.currentTarget.dataset.index);
      const item = items[index];

      if (!item || index === this.data.selected) {
        return;
      }

      this.setData({ selected: index });
      wx.switchTab({ url: `/${item.pagePath}` });
    },
  },
});
