(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pages/report/report"],
  {
    "0ed7": function (t, e, n) {
      "use strict";
      (function (t, e) {
        var o = n("47a9");
        n("86d2"), o(n("3240"));
        var i = o(n("cc41"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = n), e(i.default);
      }).call(this, n("3223").default, n("df3c").createPage);
    },
    1851: function (t, e, n) {
      "use strict";
      n.d(e, "b", function () {
        return i;
      }),
        n.d(e, "c", function () {
          return r;
        }),
        n.d(e, "a", function () {
          return o;
        });
      var o = {
          uButton: function () {
            return n
              .e("uview-ui/components/u-button/u-button")
              .then(n.bind(null, "d5d3"));
          },
          uGap: function () {
            return n
              .e("uview-ui/components/u-gap/u-gap")
              .then(n.bind(null, "2fb0"));
          },
          nodata: function () {
            return n.e("components/nodata/nodata").then(n.bind(null, "4c3d"));
          },
          ffBottomLogo: function () {
            return n
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(n.bind(null, "3111"));
          },
          confirmModal: function () {
            return n
              .e("components/confirm-modal/confirm-modal")
              .then(n.bind(null, "4e5b"));
          },
        },
        i = function () {
          var t = this,
            e = (t.$createElement, t._self._c, t.hasPermissionInfo(41)),
            n = e ? t.imgsrc("imgs/202501/refresh.png") : null,
            o = e ? t.imgsrc("imgs/202501/right_arrow.png") : null,
            i =
              e && t.isShowTotalPrice
                ? t.imgsrc("/static/imgs/open_eye_icon.png")
                : null,
            r =
              e && !t.isShowTotalPrice
                ? t.imgsrc("/static/imgs/close_eye_icon.png")
                : null,
            s = e ? t.imgsrc("imgs/202501/right_arrow.png") : null,
            a = t.hasPermissionInfo(42),
            c = a ? t.imgsrc("imgs/202501/explain.png") : null,
            u = a ? t.imgsrc("imgs/202501/refresh.png") : null,
            m = t.hasPermissionInfo(43),
            l = m ? t.imgsrc("imgs/202501/explain.png") : null,
            h = m ? t.imgsrc("imgs/202501/refresh.png") : null,
            f = m ? t.hasPermissionInfo(44) : null,
            g = m && f ? t.imgsrc("imgs/202501/explain.png") : null,
            d = m && f ? t.imgsrc("imgs/202501/refresh.png") : null,
            p = m ? t.hasPermissionInfo(45) : null,
            w = m && p ? t.imgsrc("imgs/202501/recharge-report.png") : null,
            C = m && p ? t.imgsrc("imgs/202501/course-report.png") : null,
            b = m && p ? t.imgsrc("imgs/202501/consumption-report.png") : null,
            v = m && p ? t.imgsrc("imgs/202501/ranking-report.png") : null,
            y = m && p ? t.imgsrc("imgs/202501/vip-report.png") : null,
            T = m && p ? t.imgsrc("imgs/202501/personal-report.png") : null,
            S = m && p ? t.imgsrc("imgs/202501/change-report.png") : null,
            x = t.hasPermissionInfo(46),
            P = x ? t.imgsrc("imgs/202501/teamcard.png") : null,
            _ = x ? t.imgsrc("imgs/202501/private_course.png") : null,
            D = t.hasPermissionInfo(47),
            M = D ? t.imgsrc("imgs/202501/class_hour.png") : null,
            F = D ? t.imgsrc("imgs/202501/personal.png") : null,
            R = D ? t.imgsrc("imgs/202501/membership_salary.png") : null,
            A = t.hasPermissionInfo(48),
            I = A ? t.imgsrc("imgs/202501/explain.png") : null,
            B = A ? t.imgsrc("imgs/202501/refresh.png") : null;
          t._isMounted ||
            ((t.e0 = function (e) {
              t.jumpRemind(
                "/pageReport/userCost/userCost?siteCost=" +
                  JSON.stringify(t.siteCost) +
                  "&index=1",
              );
            }),
            (t.e1 = function (e) {
              t.jumpRemind(
                "/pageReport/userCost/userCost?siteCost=" +
                  JSON.stringify(t.siteCost) +
                  "&index=2",
              );
            }),
            (t.e2 = function (e) {
              t.jumpRemind(
                "/pageReport/userCost/userCost?siteCost=" +
                  JSON.stringify(t.siteCost) +
                  "&index=3",
              );
            })),
            (t.$mp.data = Object.assign(
              {},
              {
                $root: {
                  m0: e,
                  m1: n,
                  m2: o,
                  m3: i,
                  m4: r,
                  m5: s,
                  m6: a,
                  m7: c,
                  m8: u,
                  m9: m,
                  m10: l,
                  m11: h,
                  m12: f,
                  m13: g,
                  m14: d,
                  m15: p,
                  m16: w,
                  m17: C,
                  m18: b,
                  m19: v,
                  m20: y,
                  m21: T,
                  m22: S,
                  m23: x,
                  m24: P,
                  m25: _,
                  m26: D,
                  m27: M,
                  m28: F,
                  m29: R,
                  m30: A,
                  m31: I,
                  m32: B,
                },
              },
            ));
        },
        r = [];
    },
    "7fd2": function (t, e, n) {
      "use strict";
      var o = n("8f03");
      n.n(o).a;
    },
    "8f03": function (t, e, n) {},
    9955: function (t, e, n) {
      "use strict";
      n.r(e);
      var o = n("fbd7"),
        i = n.n(o);
      for (var r in o)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return o[t];
            });
          })(r);
      e.default = i.a;
    },
    cc41: function (t, e, n) {
      "use strict";
      n.r(e);
      var o = n("1851"),
        i = n("9955");
      for (var r in i)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return i[t];
            });
          })(r);
      n("7fd2");
      var s = n("828b"),
        a = Object(s.a)(
          i.default,
          o.b,
          o.c,
          !1,
          null,
          "a91207a8",
          null,
          !1,
          o.a,
          void 0,
        );
      e.default = a.exports;
    },
    fbd7: function (t, e, n) {
      "use strict";
      (function (t) {
        var o = n("47a9");
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0);
        var i = o(n("7eb4")),
          r = o(n("ee10")),
          s = o(n("7ca3")),
          a = n("4689"),
          c = o(n("0e7a")),
          u = (n("8337"), {}),
          m = {
            components: {
              customNavigation: function () {
                n.e("pages/course/components/custom-navigation")
                  .then(
                    function () {
                      return resolve(n("ba6c"));
                    }.bind(null, n),
                  )
                  .catch(n.oe);
              },
              confirm: function () {
                n.e("components/confirm-modal/confirm-modal")
                  .then(
                    function () {
                      return resolve(n("4e5b"));
                    }.bind(null, n),
                  )
                  .catch(n.oe);
              },
              expiredAlert: function () {
                Promise.all([
                  n.e("common/vendor"),
                  n.e("components/expiredAlert/expiredAlert"),
                ])
                  .then(
                    function () {
                      return resolve(n("f411"));
                    }.bind(null, n),
                  )
                  .catch(n.oe);
              },
              confirmModal: function () {
                n.e("components/confirm-modal/confirm-modal")
                  .then(
                    function () {
                      return resolve(n("4e5b"));
                    }.bind(null, n),
                  )
                  .catch(n.oe);
              },
            },
            data: function () {
              return {
                isShowTotalPrice: !0,
                scrollTop: 0,
                data: {},
                totalProfit: "--",
                nowMonth: "",
                nowYear: "",
                month12: [],
                localMonth: {},
                cWidth: 640,
                cHeight: 385,
                nowCash: {},
                nowCourse: {},
                remindInfo: {},
                userAnalyze: {},
                cardAnalyze: {},
                siteCost: {},
                chartDataList: {
                  categories: [],
                  series: [
                    {
                      name: "累计收入",
                      data: [],
                      formatter: function (t, e, n, o) {
                        return 0 == t ? "" : t;
                      },
                    },
                  ],
                },
                chartData: null,
                canvasId: "vGZcjyxwXRIRYoGsrHCHcsXrnsNWRbSx",
                customStyle: {
                  width: "167rpx",
                  height: "60rpx",
                  background: "#FFFFFF",
                  borderRadius: "35rpx",
                  color: "#223F60",
                  borderColor: "#d3dde9",
                  padding: "0 6rpx",
                },
                updateTime: "",
                updateTimeData: 1,
                num: 0,
              };
            },
            computed: {
              hasAllPermission: function () {
                var t = this;
                return [41, 42, 43, 44, 45, 46, 47, 48].some(function (e) {
                  return t.$store.getters.getUserFunc(e);
                });
              },
              StatusBar: function () {
                return this.$store.state.systemInfo.statusBarHeight;
              },
              CustomBar: function () {
                var e = t.getMenuButtonBoundingClientRect();
                return (
                  e.height +
                  2 * (e.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                  2
                );
              },
            },
            methods: {
              handleShowTotalPrice: function () {
                (this.isShowTotalPrice = !this.isShowTotalPrice),
                  t.setStorageSync("isShowTotalPrice", this.isShowTotalPrice);
              },
              hasPermissionInfo: function (t) {
                return this.$store.getters.getUserFunc(t);
              },
              refreshclick: function () {
                this.reconsumption();
              },
              succCconfirmbtn: function () {
                this.$refs.succConfirmModal.show = !1;
              },
              ljconsumption: function () {
                var e = this;
                (this.num = this.getDailyCache("storage_recount_report") || 0),
                  this.num >= 5
                    ? t.showToast({
                        icon: "none",
                        title: "待今日凌晨自动更新 ",
                      })
                    : (0, a.computeAgain)().then(function (n) {
                        200 == n.code
                          ? ((e.num = e.num + 1),
                            e.setDailyCache("storage_recount_report", e.num),
                            (e.$refs.consumptionConfirmModal.show = !1),
                            (e.$refs.succConfirmModal.show = !0))
                          : t.showToast({ icon: "none", title: n.msg });
                      });
              },
              consumptionhandleCancelbtn: function () {
                this.$refs.consumptionConfirmModal.show = !1;
              },
              reconsumption: function () {
                this.$refs.consumptionConfirmModal.show = !0;
              },
              setDailyCache: function (e, n) {
                var o = new Date();
                o.setHours(24, 0, 0, 0);
                var i = o.getTime();
                t.setStorageSync(e, { data: n, expireTime: i });
              },
              getDailyCache: function (e) {
                var n = t.getStorageSync(e);
                return n
                  ? Date.now() >= n.expireTime
                    ? (t.removeStorageSync(e), null)
                    : n.data
                  : null;
              },
              drawCharts: function (e, n) {
                var o = this;
                t.createSelectorQuery()
                  .in(this)
                  .select("#".concat(e))
                  .fields({ node: !0, size: !0 })
                  .exec(function (i) {
                    var r = i[0].node,
                      a = r.getContext("2d"),
                      m = t.getSystemInfoSync().pixelRatio;
                    (r.width = i[0].width * m),
                      (r.height = i[0].height * m),
                      a.scale(m, m),
                      (u[e] = new c.default({
                        type: "column",
                        context: a,
                        width: o.cWidth,
                        height: o.cHeight,
                        categories: n.categories,
                        series: n.series,
                        animation: !0,
                        background: "#FFFFFF",
                        color: [
                          "#FAC858",
                          "#EE6666",
                          "#FAC858",
                          "#EE6666",
                          "#73C0DE",
                          "#3CA272",
                          "#FC8452",
                          "#9A60B4",
                          "#ea7ccc",
                        ],
                        padding: [15, 15, 0, 5],
                        legend: { show: !1 },
                        xAxis: { disableGrid: !0, fontSize: 9 },
                        yAxis: {
                          disableGrid: !0,
                          data: [{ min: 0, fontSize: 1 }],
                        },
                        extra: {
                          column: (0, s.default)(
                            {
                              type: "group",
                              activeBgColor: "#000000",
                              linearType: "custom",
                              barBorderCircle: !0,
                              customColor: ["#FA7D8D", "#EB88E2"],
                              width: 14,
                              seriesGap: 5,
                            },
                            "barBorderCircle",
                            !0,
                          ),
                        },
                      }));
                  });
              },
              explain: function (t) {
                "remind" == t &&
                  ((this.updateTime =
                    this.data.remindInfo_computeTime.computeTime),
                  (this.updateTimeData = 1)),
                  "member" == t &&
                    ((this.updateTime =
                      this.data.userAnalyze_computeTime.computeTime),
                    (this.updateTimeData = 2)),
                  "memberCard" == t &&
                    ((this.updateTime =
                      this.data.cardAnalyze_computeTime.computeTime),
                    (this.updateTimeData = 3)),
                  "siteCost" == t &&
                    ((this.updateTime = this.data.siteCost.dateVal),
                    (this.updateTimeData = 4)),
                  (this.$refs.confirmModal.show = !0);
              },
              confirmbtnFail: function () {
                this.$refs.confirmModal.show = !1;
              },
              getList: function () {
                var t = this;
                return (0, r.default)(
                  i.default.mark(function e() {
                    return i.default.wrap(function (e) {
                      for (;;)
                        switch ((e.prev = e.next)) {
                          case 0:
                            return (
                              (e.next = 2),
                              (0, a.mainpage)().then(function (e) {
                                (t.data = e),
                                  (t.totalProfit = e.totalProfit),
                                  (t.month12 = e.month12),
                                  (t.chartData = e.month12),
                                  (t.nowMonth = e.nowMonth),
                                  (t.nowYear = e.nowYear),
                                  (t.localMonth = e.localMonth),
                                  (t.nowCash = e.nowCash),
                                  (t.nowCourse = e.nowCourse),
                                  (t.remindInfo = e.remindInfo),
                                  (t.siteCost = e.siteCost),
                                  (t.userAnalyze = e.userAnalyze),
                                  (t.cardAnalyze = e.cardAnalyze),
                                  t.month12.map(function (e) {
                                    e.month == t.nowMonth && (e.month = "本");
                                  });
                                for (var n = 0; n < t.month12.length; n++)
                                  (t.chartDataList.categories[n] =
                                    t.month12[n].month + "月"),
                                    (t.chartDataList.series[0].data[n] =
                                      t.month12[n].value),
                                    (t.chartDataList.series[0].textSize = 10);
                                t.drawCharts(t.canvasId, t.chartDataList);
                              })
                            );
                          case 2:
                          case "end":
                            return e.stop();
                        }
                    }, e);
                  }),
                )();
              },
              jumpModal: function (e) {
                "businessData" == e
                  ? t.navigateTo({
                      url:
                        "/pageReport/income/businessData?strmonth=" +
                        this.nowCash.monthNum +
                        "&year=" +
                        this.nowCash.yearNum,
                    })
                  : "courseReportFormMonth" == e &&
                    t.navigateTo({
                      url:
                        "/pageReport/course/courseReportFormMonth?strmonth=" +
                        this.nowCourse.month +
                        "&year=" +
                        this.nowCourse.year,
                    });
              },
              jumpRemind: function (e) {
                t.navigateTo({ url: e });
              },
              jumpPageMember: function (e) {
                t.navigateTo({ url: e });
              },
              headleMonthlyDetails: function () {
                t.navigateTo({ url: "/pageReport/income/businessReportForm" });
              },
              courseMonthlyDetails: function () {
                t.navigateTo({ url: "/pageReport/course/courseReportForm" });
              },
            },
            onLoad: function (e) {
              var n = t.getStorageSync("isShowTotalPrice");
              (this.isShowTotalPrice = !1 !== n),
                (this.num = this.getDailyCache("storage_recount_report") || 0);
            },
            onReady: function () {
              (this.cWidth = t.upx2px(660)),
                (this.cHeight = t.upx2px(385)),
                this.hasAllPermission && this.getList();
            },
            onShow: function () {
              t.getStorageSync("report") &&
                t.pageScrollTo({ scrollTop: 1100, duration: 300 }),
                t.setStorageSync("report", !1);
            },
            onPullDownRefresh: function () {
              this.hasAllPermission && this.getList(), t.stopPullDownRefresh();
            },
          };
        e.default = m;
      }).call(this, n("df3c").default);
    },
  },
  [["0ed7", "common/runtime", "common/vendor"]],
]);
