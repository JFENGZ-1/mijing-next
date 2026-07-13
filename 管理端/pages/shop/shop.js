(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pages/shop/shop"],
  {
    "670c": function (n, i, e) {},
    "6b23": function (n, i, e) {
      "use strict";
      e.d(i, "b", function () {
        return o;
      }),
        e.d(i, "c", function () {
          return s;
        }),
        e.d(i, "a", function () {
          return t;
        });
      var t = {
          uIcon: function () {
            return e
              .e("uview-ui/components/u-icon/u-icon")
              .then(e.bind(null, "81af"));
          },
          nodata: function () {
            return e.e("components/nodata/nodata").then(e.bind(null, "4c3d"));
          },
          confirmModal: function () {
            return e
              .e("components/confirm-modal/confirm-modal")
              .then(e.bind(null, "4e5b"));
          },
        },
        o = function () {
          var n = this,
            i =
              (n.$createElement,
              n._self._c,
              n.siteinfo ? n.$shorten(n.siteinfo.siteName, 11) : null),
            e =
              n.siteinfo && n.staff ? n.$shorten(n.siteinfo.siteName, 9) : null,
            t =
              n.siteinfo && n.staff
                ? n.$shorten(n.siteinfo.siteAddr || "填写场馆地址", 20)
                : null,
            o =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_basics
                ? n.initializeBaseConfig.length
                : null,
            s =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_basics &&
              o > 0
                ? n.baseConfig.length
                : null,
            a =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_basics &&
              o > 0
                ? n.baseConfig.length
                : null,
            r =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_basics &&
              o > 0
                ? n.baseConfig.length
                : null,
            f =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_basics &&
              o > 0
                ? n.baseConfig.length
                : null,
            c =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_basics &&
              o > 0
                ? n.imgsrc("/static/imgs/slogan_img.png?a=111")
                : null,
            u =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_basics &&
              o > 0
                ? n.imgsrc("/static/imgs/perfect_info_img.png?a=111")
                : null,
            p =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_basics &&
              o > 0
                ? n.__map(n.initializeBaseConfig, function (i, e) {
                    return {
                      $orig: n.__get_orig(i),
                      g5: n.initializeBaseConfig.length,
                      m5: n.imgsrc(i.src),
                    };
                  })
                : null,
            g =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_basics
                ? n.baseConfig.length
                : null,
            l =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_basics &&
              g > 0
                ? n.initializeBaseConfig.length
                : null,
            m =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_basics &&
              g > 0
                ? n.__map(n.baseConfig, function (i, e) {
                    return { $orig: n.__get_orig(i), m6: n.imgsrc(i.src) };
                  })
                : null,
            d =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_default
                ? n.__map(n.defaultConfigList, function (i, e) {
                    return { $orig: n.__get_orig(i), m7: n.imgsrc(i.src) };
                  })
                : null,
            h =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_member_config
                ? n.__map(n.memberConfigList, function (i, e) {
                    return { $orig: n.__get_orig(i), m8: n.imgsrc(i.src) };
                  })
                : null,
            b =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_manager_tool
                ? n.__map(n.shopManagerToolList, function (i, e) {
                    return { $orig: n.__get_orig(i), m9: n.imgsrc(i.src) };
                  })
                : null,
            I =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.multiple_shop_config
                ? n.__map(n.multipleList, function (i, e) {
                    return { $orig: n.__get_orig(i), m10: n.imgsrc(i.src) };
                  })
                : null,
            v =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_service_center
                ? n.imgsrc("/static/imgs/shop_service_left_icon.png")
                : null,
            _ =
              n.siteinfo &&
              n.staff &&
              n.permissionInfo &&
              n.permissionInfo.shop_service_center
                ? n.imgsrc("/static/imgs/video_help_icon.png")
                : null,
            C =
              n.siteinfo && n.staff
                ? n.imgsrc("/static/imgs/bottom_logo_1.png")
                : null;
          n._isMounted ||
            ((n.e0 = function (i) {
              n.permissionInfo.shop_basics &&
                n.headeJump("/pagesImp/shop/setting/store/store-setting");
            }),
            (n.e1 = function (i) {
              n.permissionInfo.shop_basics &&
                n.headeJump("/pagesImp/shop/setting/store/store-setting");
            }),
            (n.e2 = function (i) {
              n.permissionInfo.shop_basics &&
                n.headeJump("/pagesImp/shop/setting/store/store-setting");
            }),
            (n.e3 = function (i) {
              n.permissionInfo.shop_basics &&
                n.headeJump("/pagesImp/shop/setting/store/store-setting");
            }),
            (n.e4 = function (i) {
              for (var e = [], t = arguments.length - 1; t-- > 0; )
                e[t] = arguments[t + 1];
              var o = e[e.length - 1].currentTarget.dataset,
                s = o.eventParams || o["event-params"];
              (i = s.item).disable || n.routerPermission(i);
            })),
            (n.$mp.data = Object.assign(
              {},
              {
                $root: {
                  m0: i,
                  m1: e,
                  m2: t,
                  g0: o,
                  g1: s,
                  g2: a,
                  g3: r,
                  g4: f,
                  m3: c,
                  m4: u,
                  l0: p,
                  g6: g,
                  g7: l,
                  l1: m,
                  l2: d,
                  l3: h,
                  l4: b,
                  l5: I,
                  m11: v,
                  m12: _,
                  m13: C,
                },
              },
            ));
        },
        s = [];
    },
    "806f": function (n, i, e) {
      "use strict";
      (function (n, t) {
        var o = e("47a9");
        Object.defineProperty(i, "__esModule", { value: !0 }),
          (i.default = void 0);
        var s = o(e("7ca3")),
          a = o(e("3b2d")),
          r = e("f24f"),
          f = e("6b61"),
          c = e("073c"),
          u = o(e("7502"));
        function p(n, i) {
          var e = Object.keys(n);
          if (Object.getOwnPropertySymbols) {
            var t = Object.getOwnPropertySymbols(n);
            i &&
              (t = t.filter(function (i) {
                return Object.getOwnPropertyDescriptor(n, i).enumerable;
              })),
              e.push.apply(e, t);
          }
          return e;
        }
        function g(n) {
          for (var i = 1; i < arguments.length; i++) {
            var e = null != arguments[i] ? arguments[i] : {};
            i % 2
              ? p(Object(e), !0).forEach(function (i) {
                  (0, s.default)(n, i, e[i]);
                })
              : Object.getOwnPropertyDescriptors
                ? Object.defineProperties(
                    n,
                    Object.getOwnPropertyDescriptors(e),
                  )
                : p(Object(e)).forEach(function (i) {
                    Object.defineProperty(
                      n,
                      i,
                      Object.getOwnPropertyDescriptor(e, i),
                    );
                  });
          }
          return n;
        }
        var l = {
          data: function () {
            return {
              fivePX: 5,
              fixedBarOpacity: 0,
              defaultConfigList: [
                {
                  name: "预约设置",
                  src: "/static/imgs/default-config-1.png",
                  url: "/pageConfig/appointSetting/index",
                },
                {
                  name: "提醒设置",
                  src: "/static/imgs/default-config-2.png",
                  url: "/pageConfig/reminderSettings/index",
                },
                {
                  name: "会员资料",
                  src: "/static/imgs/default-config-3.png",
                  url: "/pageConfig/membership/index",
                },
                {
                  name: "会员协议",
                  src: "/static/imgs/default-config-4.png",
                  url: "/pageConfig/embershipAgreement/index",
                },
                {
                  name: "收款帐户",
                  src: "/static/imgs/default-config-5.png",
                  url: "/pageConfig/paySetting/index",
                },
                {
                  name: "场馆码",
                  src: "/static/imgs/default-config-7.png",
                  url: "/pageConfig/appointment/index",
                },
              ],
              multipleList: [
                {
                  color: "#5fa3ea  !important",
                  name: "如何设置",
                  src: "/unioncard/multiple-config-instructions.png",
                  url: "/pageChain/instructions/index",
                },
                {
                  color: "#5fa3ea !important",
                  name: "分店管理",
                  src: "/unioncard/multiple-config-subbranch.png",
                  url: "/pageChain/storesManagement/index",
                },
                {
                  color: "#5fa3ea !important",
                  name: "连锁通用卡",
                  src: "/unioncard/multiple-config-card.png",
                  url: "/pageChain/card/home/home",
                },
                {
                  color: "#5fa3ea  !important",
                  name: "适用店与课",
                  src: "/unioncard/multiple-config-shop-course.png",
                  url: "/pageChain/card/card-subject/index",
                },
                {
                  color: "#5fa3ea  !important",
                  name: "售卡统计",
                  src: "/unioncard/multiple-config-card-statistics.png",
                  url: "/pageChain/cardStatistics/index",
                },
                {
                  color: "#5fa3ea  !important",
                  name: "课时费统计",
                  src: "/unioncard/multiple-config-course-statistics.png",
                  url: "/pageChain/courseStatistics/index",
                },
                {
                  color: "#5fa3ea  !important",
                  name: "总店员工",
                  src: "/unioncard/multiple-config-staff.png",
                  url: "/pageChain/configStaff/index",
                },
              ],
              memberConfigList: [
                {
                  color: "#f19469  !important",
                  name: "会员如何约课",
                  src: "/static/imgs/member-config-course.png",
                  url: "/pageConfig/memberConfigCourse/index",
                },
                {
                  color: "#f19469 !important",
                  name: "场馆展示图",
                  src: "/static/imgs/member-config-show.png",
                  url: "/pageConfig/memberConfigShow/index",
                },
                {
                  color: "#f19469 !important",
                  name: "温馨提示",
                  src: "/static/imgs/member-config-kind-reminder.png",
                  url: "/pageConfig/memberConfigKindReminder/index",
                },
                {
                  color: "#e7799d !important",
                  name: "显示/隐藏",
                  src: "/static/imgs/member-config-hide.png",
                  url: "/pageConfig/displayHide/index",
                },
              ],
              shopManagerToolList: [
                {
                  name: "节假日闭店",
                  src: "/static/imgs/shop-manager-tool-1.png",
                  url: "/pageConfig/stopDoing/index",
                  color: "#5fa3ea !important",
                },
                {
                  name: "教练请假",
                  src: "/static/imgs/shop-manager-tool-2.png",
                  url: "/pageConfig/coachLeave/index",
                  color: "#5fa3ea !important",
                },
                {
                  name: "发布公告",
                  src: "/static/imgs/shop-manager-tool-3.png",
                  url: "/pageConfig/notificationManagement/index",
                  color: "#5fa3ea !important",
                },
                {
                  name: "数据导出",
                  src: "/static/imgs/shop_report.png",
                  url: "/pageConfig/shopReport/index",
                  color: "#5fa3ea !important",
                },
              ],
              initializeBaseConfig: [],
              baseConfig: [],
              buyinfo: null,
              staff: null,
              siteinfo: null,
              shopInfo: null,
              userWxAvatarUrl: null,
              cardDotTips: null,
              courseDotTips: null,
            };
          },
          computed: {
            hasAllPermission: function () {
              var n = this;
              return [51, 52, 53, 54, 55].some(function (i) {
                return n.$store.getters.getUserFunc(i);
              });
            },
            headerH: function () {
              return "".concat(
                this.StatusBar + this.CustomBar + n.upx2px(130) + 25,
                "px",
              );
            },
            platform: function () {
              return this.$store.state.systemInfo.platform;
            },
            StatusBar: function () {
              return this.$store.state.systemInfo.statusBarHeight;
            },
            CustomBar: function () {
              var i = n.getMenuButtonBoundingClientRect();
              return (
                i.height +
                2 * (i.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                2
              );
            },
            permissionInfo: function () {
              return this.$store.state.permissionInfo;
            },
            dictVal: function () {
              return this.$store.state.dictVal;
            },
          },
          components: {
            expiredAlert: function () {
              Promise.all([
                e.e("common/vendor"),
                e.e("components/expiredAlert/expiredAlert"),
              ])
                .then(
                  function () {
                    return resolve(e("f411"));
                  }.bind(null, e),
                )
                .catch(e.oe);
            },
            loadingPulse: function () {
              e.e("components/zero-loading/static/loading-pulse")
                .then(
                  function () {
                    return resolve(e("c601"));
                  }.bind(null, e),
                )
                .catch(e.oe);
            },
            confirmModal: function () {
              e.e("components/confirm-modal/confirm-modal")
                .then(
                  function () {
                    return resolve(e("4e5b"));
                  }.bind(null, e),
                )
                .catch(e.oe);
            },
          },
          onPageScroll: function (n) {
            n.scrollTop < 70
              ? ((this.fixedBarOpacity = 0), (this.fivePX = 5))
              : n.scrollTop <= 90
                ? ((this.fixedBarOpacity = (n.scrollTop - 70) / 20),
                  (this.fivePX = 0))
                : ((this.fixedBarOpacity = 1), (this.fivePX = 0));
          },
          onLoad: function () {
            var i = this;
            (this.shopInfo = this.$store.state.stopInfo),
              n.$on("shopInfoOk", function (n) {
                i.shopInfo = i.$store.state.stopInfo;
              });
            var e = n.getStorageSync("authorizationInfo");
            e &&
              e.avatarUrl &&
              e.nickname &&
              e.userphone &&
              (this.userWxAvatarUrl = e.avatarUrl);
          },
          onUnload: function () {
            n.$off("shopInfoOk");
          },
          onShow: function () {
            this.getConfigInfo();
          },
          onPullDownRefresh: function () {
            this.getConfigInfo(), n.stopPullDownRefresh();
          },
          methods: {
            openCApp: function () {
              var i = this.stopInfo.siteId,
                e =
                  "object" ==
                  ("undefined" == typeof __wxConfig
                    ? "undefined"
                    : (0, a.default)(__wxConfig))
                    ? __wxConfig.envVersion
                    : "trial";
              n.navigateToMiniProgram({
                appId: u.default.openAppid,
                path: "pages/start/index?siteId=".concat(i),
                envVersion: e,
              });
            },
            routerPermission: function (i) {
              "sitecount" == i.key && this.shopInfo.isVisitor
                ? this.settingShopInfo()
                : n.navigateTo({ url: i.url });
            },
            headeJump: function (i) {
              this.shopInfo.isVisitor
                ? this.settingShopInfo()
                : n.navigateTo({ url: i });
            },
            getConfigInfo: function () {
              var i = this;
              (0, r.getSiteConfig)().then(function (e) {
                if ((n.stopPullDownRefresh(), 200 == e.code)) {
                  e.buyinfo
                    ? (e.buyinfo.endTime &&
                        (e.buyinfo.endTime = (0, c.filterDate)(
                          e.buyinfo.endTime,
                        )),
                      (i.buyinfo = e.buyinfo))
                    : (i.buyinfo = null),
                    (i.staff = e.staff),
                    (i.siteinfo = e.siteinfo),
                    (i.cardDotTips = e.noConfigCardcount),
                    (i.courseDotTips = e.noConfigCoursecount);
                  var o = [],
                    s = [],
                    a = e.data,
                    r = {
                      sitecount: a.sitecount,
                      cardcount: a.cardcount,
                      staffcount: a.staffcount,
                      coursecount: a.coursecount,
                      deductCount: a.deductCount,
                      plancount: a.plancount,
                    };
                  for (var f in r)
                    if (0 == e.data[f]) {
                      var u = null,
                        p = null,
                        g = null,
                        l = null;
                      "sitecount" == f
                        ? ((u = "1.场馆信息"),
                          (p = "/static/imgs/Initial-basics-setting-1.png"),
                          (g = "填写场馆名称与logo"),
                          (l = "/pagesImp/shop/setting/store/store-setting"))
                        : "cardcount" == f
                          ? ((u = "2.会员卡"),
                            (p = "/static/imgs/Initial-basics-setting-2.png"),
                            (g = "创建和管理您所出售的会员卡母卡"),
                            (l = "/pagesImp/card/home/home"))
                          : "staffcount" == f
                            ? ((u = "3.教练/员工"),
                              (p = "/static/imgs/Initial-basics-setting-3.png"),
                              (g = "添加教练与员工，并设置操作权限"),
                              (l = "/pagesImp/shop/staff/staff"))
                            : "coursecount" == f
                              ? ((u = "4.课程库"),
                                (p =
                                  "/static/imgs/Initial-basics-setting-4.png"),
                                (g = "创建如阿斯汤伽、垫上普拉提等课目"),
                                (l = "/pagesImp/subject/subject"))
                              : "deductCount" == f
                                ? ((u = "5.卡・课关联"),
                                  (p =
                                    "/static/imgs/Initial-basics-setting-6.png"),
                                  (g = "哪些卡可以预约哪些课，并设置课时费"),
                                  (l = "/pagesImp/card/card-subject/index"))
                                : ((u = "6.排课/课程"),
                                  (p =
                                    "/static/imgs/Initial-basics-setting-5.png"),
                                  (g = "按日期进行排课管理"),
                                  (l = "/pagesCourse/index/index")),
                        o.push({ name: u, src: p, text: g, url: l, key: f });
                    } else {
                      var m = null,
                        d = null,
                        h = null;
                      if ("cardcount" == f) {
                        if (!i.hasPermission(62)) continue;
                        (m = "会员卡"),
                          (d = "/static/imgs/basics-setting-2.png"),
                          (h = "/pagesImp/card/home/home");
                      } else if ("sitecount" == f) {
                        if (!i.hasPermission(61)) continue;
                        (m = "场馆信息"),
                          (d = "/static/imgs/basics-setting-1.png"),
                          (h = "/pagesImp/shop/setting/store/store-setting");
                      } else if ("coursecount" == f) {
                        if (!i.hasPermission(64)) continue;
                        (m = "课程库"),
                          (d = "/static/imgs/basics-setting-4.png"),
                          (h = "/pagesImp/subject/subject");
                      } else if ("staffcount" == f) {
                        if (!i.hasPermission(63)) continue;
                        (m = "教练/员工"),
                          (d = "/static/imgs/basics-setting-3.png"),
                          (h = "/pagesImp/shop/staff/staff");
                      } else if ("plancount" == f) {
                        if (!i.hasPermission(66)) continue;
                        (m = "排课/课程"),
                          (d = "/static/imgs/basics-setting-5.png"),
                          (h = "/pagesCourse/index/index");
                      } else {
                        if (!i.hasPermission(65)) continue;
                        (m = "卡・课关联"),
                          (d = "/static/imgs/basics-setting-6.png"),
                          (h = "/pagesImp/card/card-subject/index");
                      }
                      s.push({ name: m, src: d, url: h });
                    }
                  (i.initializeBaseConfig = o),
                    (i.baseConfig = s),
                    i.initializeBaseConfig.length > 0 &&
                      (i.initializeBaseConfig.forEach(function (n) {
                        n.disable = !0;
                      }),
                      (i.initializeBaseConfig[0].disable = !1));
                } else t.showToast({ title: "您的网络不稳定", mask: "none" });
              });
            },
            jumpShopService: function () {
              var i = this;
              this.shopInfo.isVisitor
                ? this.settingShopInfo()
                : (n.showLoading({ title: "加载中", mask: !0 }),
                  (0, f.getSiteInfo)().then(function (e) {
                    if ((n.hideLoading(), 200 == e.code)) {
                      var t = e.data,
                        o = e.customServicer,
                        s = e.servicerFaceurl,
                        a = e.servicerNickName,
                        r = e.protocolURL,
                        f = g(
                          g({}, t),
                          {},
                          {
                            customServicer: o,
                            servicerFaceurl: s,
                            servicerNickName: a,
                            protocolURL: r,
                          },
                        );
                      i.$store.dispatch("getStopServeInfo", f),
                        i.href({ url: "/pageServer/index" });
                    } else n.showToast({ title: e.msg });
                  }));
            },
            jumpVideoHelp: function () {
              this.href({ url: "/pageServer/videoHelp/videoHelp?source=shop" });
            },
            renew: function () {
              var i = this;
              n.showLoading({ title: "加载中", mask: !0 }),
                (0, f.getSiteInfo)().then(function (e) {
                  if ((n.hideLoading(), 200 == e.code)) {
                    var t = e.data,
                      o = e.customServicer,
                      s = e.servicerFaceurl,
                      a = e.servicerNickName,
                      r = g(
                        g({}, t),
                        {},
                        {
                          customServicer: o,
                          servicerFaceurl: s,
                          servicerNickName: a,
                        },
                      );
                    i.$store.dispatch("getStopServeInfo", r),
                      i.href({ url: "/pageServer/order" });
                  } else n.showToast({ title: e.msg });
                });
            },
            confirmCancel: function () {
              this.$refs.confirmModal.show = !1;
            },
            settingInfo: function () {
              (this.$refs.confirmModal.show = !1), this.settingShopInfo();
            },
            settingShopInfo: function () {
              var i = n.getStorageSync("authorizationInfo");
              i && i.avatarUrl && i.nickname && i.userphone
                ? n.navigateTo({
                    url: "/pagesImp/shop/setting/store/store-setting?id=storesManagement",
                  })
                : n.navigateTo({
                    url: "/pages/shop/authorizationPage/info/index",
                  });
            },
            jumpModal: function (i) {
              if (this.shopInfo.isVisitor)
                return (this.$refs.confirmModal.show = !0), !1;
              n.navigateTo({ url: i });
            },
          },
        };
        i.default = l;
      }).call(this, e("df3c").default, e("3223").default);
    },
    bd69: function (n, i, e) {
      "use strict";
      e.r(i);
      var t = e("806f"),
        o = e.n(t);
      for (var s in t)
        ["default"].indexOf(s) < 0 &&
          (function (n) {
            e.d(i, n, function () {
              return t[n];
            });
          })(s);
      i.default = o.a;
    },
    f3af: function (n, i, e) {
      "use strict";
      e.r(i);
      var t = e("6b23"),
        o = e("bd69");
      for (var s in o)
        ["default"].indexOf(s) < 0 &&
          (function (n) {
            e.d(i, n, function () {
              return o[n];
            });
          })(s);
      e("f674");
      var a = e("828b"),
        r = Object(a.a)(
          o.default,
          t.b,
          t.c,
          !1,
          null,
          "ccc6af4c",
          null,
          !1,
          t.a,
          void 0,
        );
      i.default = r.exports;
    },
    f674: function (n, i, e) {
      "use strict";
      var t = e("670c");
      e.n(t).a;
    },
    f818: function (n, i, e) {
      "use strict";
      (function (n, i) {
        var t = e("47a9");
        e("86d2"), t(e("3240"));
        var o = t(e("f3af"));
        (n.__webpack_require_UNI_MP_PLUGIN__ = e), i(o.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
  },
  [["f818", "common/runtime", "common/vendor"]],
]);
