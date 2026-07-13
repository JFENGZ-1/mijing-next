(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageServer/order"],
  {
    "01d3": function (t, e, n) {},
    "1f0d": function (t, e, n) {
      "use strict";
      (function (t, e) {
        var i = n("47a9");
        n("86d2"), i(n("3240"));
        var o = i(n("823e"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = n), e(o.default);
      }).call(this, n("3223").default, n("df3c").createPage);
    },
    "3c96": function (t, e, n) {
      "use strict";
      (function (t, i) {
        var o = n("47a9");
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0);
        var r = o(n("7ca3")),
          c = o(n("3387")),
          s = n("6b61");
        function u(t, e) {
          var n = Object.keys(t);
          if (Object.getOwnPropertySymbols) {
            var i = Object.getOwnPropertySymbols(t);
            e &&
              (i = i.filter(function (e) {
                return Object.getOwnPropertyDescriptor(t, e).enumerable;
              })),
              n.push.apply(n, i);
          }
          return n;
        }
        function a(t) {
          for (var e = 1; e < arguments.length; e++) {
            var n = null != arguments[e] ? arguments[e] : {};
            e % 2
              ? u(Object(n), !0).forEach(function (e) {
                  (0, r.default)(t, e, n[e]);
                })
              : Object.getOwnPropertyDescriptors
                ? Object.defineProperties(
                    t,
                    Object.getOwnPropertyDescriptors(n),
                  )
                : u(Object(n)).forEach(function (e) {
                    Object.defineProperty(
                      t,
                      e,
                      Object.getOwnPropertyDescriptor(n, e),
                    );
                  });
          }
          return t;
        }
        var f = {
          data: function () {
            return {
              infoH: null,
              navActive: 0,
              custom_style: {
                width: "458rpx",
                height: "83rpx",
                background: "linear-gradient(90deg, #F6DAB3, #F7D29F)",
                fontSize: "32rpx",
                borderRadius: "41rpx",
                color: "#8F5700",
              },
              priceList: [],
              popupShow: !1,
              orderStatus: "success",
              secretKey: "",
            };
          },
          components: {
            navigation: function () {
              n.e("components/navigation/index")
                .then(
                  function () {
                    return resolve(n("af9e"));
                  }.bind(null, n),
                )
                .catch(n.oe);
            },
            info: function () {
              Promise.all([
                n.e("common/vendor"),
                n.e("pageServer/components/info"),
              ])
                .then(
                  function () {
                    return resolve(n("e6fb"));
                  }.bind(null, n),
                )
                .catch(n.oe);
            },
          },
          onLoad: function () {
            var e = this;
            this.$nextTick(function () {
              var n = t.createSelectorQuery().in(e);
              n.select(".info").boundingClientRect(),
                n.exec(function (t) {
                  e.infoH = t[0].height;
                });
            }),
              this.getPriceList();
          },
          methods: {
            toggleNav: function (t) {
              this.navActive = t;
            },
            priceClick: function (t) {
              this.priceList.forEach(function (t) {
                t.active = !1;
              }),
                (this.priceList[t].active = !0),
                this.$forceUpdate();
            },
            getPriceList: function () {
              var t = this;
              (0, s.pricelist)({}).then(function (e) {
                (e.list[0].active = !0), (t.priceList = e.list);
              });
            },
            priceConfrim: function () {
              var e = c.default.find(this.priceList, function (t) {
                  return 1 == t.active;
                }),
                n = {};
              n.configid = e.configId;
              var o = this;
              (0, s.submitwexinOrder)(n).then(function (e) {
                var n = e.data;
                i.requestPayment({
                  timeStamp: n.timeStamp,
                  nonceStr: n.nonceStr,
                  package: n.packageStr,
                  signType: n.signType,
                  paySign: n.paySign,
                  success: function (e) {
                    (0, s.getSiteInfo)().then(function (e) {
                      if ((t.hideLoading(), 200 == e.code)) {
                        var n = e.data,
                          i = e.customServicer,
                          r = e.servicerNickName,
                          c = e.protocolURL,
                          s = a(
                            a({}, n),
                            {},
                            {
                              customServicer: i,
                              servicerNickName: r,
                              protocolURL: c,
                            },
                          );
                        o.$store.dispatch("getStopServeInfo", s),
                          o.$store.commit("SET_SOFTEXPIRE", null),
                          t.navigateTo({
                            url: "/pages/shopOrder/index?source=mine",
                          });
                      } else t.showToast({ title: e.msg });
                    });
                  },
                  fail: function (e) {
                    t.showToast({ title: "已取消", icon: "none" });
                  },
                  complete: function (t) {},
                });
              });
            },
            hintConfirm: function () {
              this.popupShow = !1;
            },
            secretKeyConfrim: function () {
              var e = this,
                n = this;
              if (!this.secretKey)
                return t.showToast({ title: "请输入密钥", icon: "none" }), !1;
              (0, s.submitSecretkey)({ key: this.secretKey })
                .then(function (i) {
                  (e.orderStatus = 200 == i.code ? "success" : "error"),
                    (e.popupShow = !0),
                    200 == i.code &&
                      (0, s.getSiteInfo)().then(function (e) {
                        if (200 == e.code) {
                          var i = e.data,
                            o = e.customServicer,
                            r = e.servicerNickName,
                            c = e.protocolURL,
                            s = a(
                              a({}, i),
                              {},
                              {
                                customServicer: o,
                                servicerNickName: r,
                                protocolURL: c,
                              },
                            );
                          n.$store.dispatch("getStopServeInfo", s);
                        } else t.showToast({ title: e.msg });
                      });
                })
                .catch(function (t) {
                  (e.orderStatus = "error"), (e.popupShow = !0);
                });
            },
            confirm: function () {
              (this.popupShow = !1),
                "success" == this.orderStatus &&
                  setTimeout(function () {
                    t.navigateBack({ delta: 1 });
                  }, 500);
            },
          },
          computed: {
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
        };
        e.default = f;
      }).call(this, n("df3c").default, n("3223").default);
    },
    4359: function (t, e, n) {
      "use strict";
      n.r(e);
      var i = n("3c96"),
        o = n.n(i);
      for (var r in i)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return i[t];
            });
          })(r);
      e.default = o.a;
    },
    "499e": function (t, e, n) {
      "use strict";
      n.d(e, "b", function () {
        return o;
      }),
        n.d(e, "c", function () {
          return r;
        }),
        n.d(e, "a", function () {
          return i;
        });
      var i = {
          uButton: function () {
            return n
              .e("uview-ui/components/u-button/u-button")
              .then(n.bind(null, "d5d3"));
          },
          ffBottomLogo: function () {
            return n
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(n.bind(null, "3111"));
          },
          ffPopup: function () {
            return n
              .e("components/ff-popup/ff-popup")
              .then(n.bind(null, "c29b"));
          },
        },
        o = function () {
          var t = this,
            e =
              (t.$createElement,
              t._self._c,
              0 == t.navActive
                ? t.imgsrc("/static/imgs/order-nav-img2.png")
                : null),
            n =
              0 == t.navActive
                ? t.imgsrc("/static/imgs/order-nav-image.png")
                : null,
            i =
              1 == t.navActive
                ? t.imgsrc("/static/imgs/order-nav-img2.png")
                : null,
            o =
              1 == t.navActive
                ? t.imgsrc("/static/imgs/order-nav-image.png")
                : null,
            r =
              "success" == t.orderStatus
                ? t.imgsrc("/static/imgs/success.png")
                : null,
            c =
              "success" != t.orderStatus
                ? t.imgsrc("/static/imgs/importantNote.png")
                : null;
          t.$mp.data = Object.assign(
            {},
            { $root: { m0: e, m1: n, m2: i, m3: o, m4: r, m5: c } },
          );
        },
        r = [];
    },
    "823e": function (t, e, n) {
      "use strict";
      n.r(e);
      var i = n("499e"),
        o = n("4359");
      for (var r in o)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return o[t];
            });
          })(r);
      n("d2b4"), n("9a4e");
      var c = n("828b"),
        s = Object(c.a)(
          o.default,
          i.b,
          i.c,
          !1,
          null,
          "26ce7160",
          null,
          !1,
          i.a,
          void 0,
        );
      e.default = s.exports;
    },
    "9a4e": function (t, e, n) {
      "use strict";
      var i = n("ecb3");
      n.n(i).a;
    },
    d2b4: function (t, e, n) {
      "use strict";
      var i = n("01d3");
      n.n(i).a;
    },
    ecb3: function (t, e, n) {},
  },
  [["1f0d", "common/runtime", "common/vendor"]],
]);
