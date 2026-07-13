require("../common/vendor.js"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["pageConfig/displayHide/index"],
    {
      "0886": function (n, e, t) {
        "use strict";
        t.r(e);
        var o = t("50af"),
          i = t.n(o);
        for (var c in o)
          ["default"].indexOf(c) < 0 &&
            (function (n) {
              t.d(e, n, function () {
                return o[n];
              });
            })(c);
        e.default = i.a;
      },
      "50af": function (n, e, t) {
        "use strict";
        (function (n) {
          var o = t("47a9");
          Object.defineProperty(e, "__esModule", { value: !0 }),
            (e.default = void 0);
          var i = o(t("7ca3")),
            c = t("7fc0");
          function r(n, e) {
            var t = Object.keys(n);
            if (Object.getOwnPropertySymbols) {
              var o = Object.getOwnPropertySymbols(n);
              e &&
                (o = o.filter(function (e) {
                  return Object.getOwnPropertyDescriptor(n, e).enumerable;
                })),
                t.push.apply(t, o);
            }
            return t;
          }
          function u(n) {
            for (var e = 1; e < arguments.length; e++) {
              var t = null != arguments[e] ? arguments[e] : {};
              e % 2
                ? r(Object(t), !0).forEach(function (e) {
                    (0, i.default)(n, e, t[e]);
                  })
                : Object.getOwnPropertyDescriptors
                  ? Object.defineProperties(
                      n,
                      Object.getOwnPropertyDescriptors(t),
                    )
                  : r(Object(t)).forEach(function (e) {
                      Object.defineProperty(
                        n,
                        e,
                        Object.getOwnPropertyDescriptor(t, e),
                      );
                    });
            }
            return n;
          }
          o(t("3387"));
          var s = {
            name: "index",
            data: function () {
              return {
                isMust: !1,
                schematicShow: !1,
                schematicSrc: null,
                list: [],
                cHome: {},
                cCourse: [],
                cMine: [],
                refuse: [],
                Other: [],
                refuseChecked: !1,
                configId: "",
                customStyle: {
                  width: "217rpx",
                  height: "69rpx",
                  background: "#FFCF00",
                  borderRadius: "35rpx",
                  color: "#181818",
                  borderColor: "#FFCF00",
                },
              };
            },
            components: {
              navigation: function () {
                t.e("components/navigation/index")
                  .then(
                    function () {
                      return resolve(t("af9e"));
                    }.bind(null, t),
                  )
                  .catch(t.oe);
              },
              confirmModal: function () {
                t.e("components/confirm-modal/confirm-modal")
                  .then(
                    function () {
                      return resolve(t("4e5b"));
                    }.bind(null, t),
                  )
                  .catch(t.oe);
              },
            },
            computed: {
              StatusBar: function () {
                return this.$store.state.systemInfo.statusBarHeight;
              },
              CustomBar: function () {
                var e = n.getMenuButtonBoundingClientRect();
                return (
                  e.height +
                  2 * (e.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                  2
                );
              },
            },
            methods: {
              cancelbtn: function () {
                (this.refuseChecked = !1), (this.$refs.confirmModal.show = !1);
              },
              refuseconfirm: function () {
                var e = this;
                if (this.refuseChecked) {
                  (this.refuseChecked = !1), this.cancelbtn();
                  var t = this.list.find(function (n) {
                    return n.configId === e.configId;
                  });
                  t && (t.configValue = !t.configValue),
                    this.getsaveClientConfig(!0);
                } else
                  n.showToast({ icon: "none", title: "请先点击「我已清楚」" });
              },
              showSchematicPop: function (n) {
                (this.schematicSrc = "/static/imgs/c-client-setting/".concat(
                  n,
                  ".jpg",
                )),
                  (this.schematicShow = !0);
              },
              getList: function () {
                var n = this;
                (0, c.getClientConfig)().then(function (e) {
                  var t = e.data;
                  (n.list = t.map(function (n) {
                    return u(
                      u({}, n),
                      {},
                      { configValue: 1 === n.configValue },
                    );
                  })),
                    (n.cHome = n.list.filter(function (n) {
                      return ["showBuyCardBtn", "showBuyCardPrice"].includes(
                        n.configId,
                      );
                    })),
                    (n.cCourse = [
                      "showPrivateDrainer",
                      "privateShow",
                      "showPhoneOfDrainer",
                      "teamShow",
                      "showTimeoutTeamPlan",
                    ]
                      .map(function (e) {
                        return n.list.find(function (n) {
                          return n.configId === e;
                        });
                      })
                      .filter(function (n) {
                        return void 0 !== n;
                      })),
                    (n.cMine = n.list.filter(function (n) {
                      return ["showMonthRank"].includes(n.configId);
                    })),
                    (n.refuse = n.list.filter(function (n) {
                      return [
                        "refuseUserNoLogin",
                        "refuseUserZeroBalance",
                        "refuseUserCardExpired",
                      ].includes(n.configId);
                    })),
                    (n.Other = n.list.filter(function (n) {
                      return ["refuseUserFocus"].includes(n.configId);
                    }));
                });
              },
              headleStatus: function (n, e) {
                if (
                  ((this.configId = n),
                  !e ||
                    ("refuseUserNoLogin" != n &&
                      "refuseUserZeroBalance" != n &&
                      "refuseUserCardExpired" != n))
                )
                  if (e || "refuseUserFocus" != n) {
                    var t = this.list.find(function (e) {
                      return e.configId === n;
                    });
                    t && (t.configValue = e), this.getsaveClientConfig(e);
                  } else
                    this.list.forEach(function (e) {
                      e.configId == n && (e.configValue = !0);
                    }),
                      (this.$refs.confirmModal.show = !0);
                else
                  this.list.forEach(function (e) {
                    e.configId == n && (e.configValue = !1);
                  }),
                    (this.$refs.confirmModal.show = !0);
              },
              getsaveClientConfig: function (e) {
                var t = this,
                  o = this.list.map(function (n) {
                    return u(
                      u({}, n),
                      {},
                      { configValue: n.configValue ? 1 : 2 },
                    );
                  });
                (0, c.saveClientConfig)({ data: o }).then(function (o) {
                  var i = e ? "已开启" : "已关闭";
                  200 == o.code
                    ? (t.getList(), n.showToast({ icon: "none", title: i }))
                    : n.showToast({ icon: "none", title: o.msg });
                });
              },
            },
            onLoad: function () {
              this.getList();
            },
          };
          e.default = s;
        }).call(this, t("df3c").default);
      },
      5769: function (n, e, t) {
        "use strict";
        var o = t("d3ed");
        t.n(o).a;
      },
      "8e10": function (n, e, t) {
        "use strict";
        t.d(e, "b", function () {
          return i;
        }),
          t.d(e, "c", function () {
            return c;
          }),
          t.d(e, "a", function () {
            return o;
          });
        var o = {
            uIcon: function () {
              return t
                .e("uview-ui/components/u-icon/u-icon")
                .then(t.bind(null, "81af"));
            },
            uSwitch: function () {
              return t
                .e("uview-ui/components/u-switch/u-switch")
                .then(t.bind(null, "a048"));
            },
            ffBottomLogo: function () {
              return t
                .e("components/ff-bottom-logo/ff-bottom-logo")
                .then(t.bind(null, "3111"));
            },
            uMask: function () {
              return t
                .e("uview-ui/components/u-mask/u-mask")
                .then(t.bind(null, "6cda"));
            },
            uButton: function () {
              return t
                .e("uview-ui/components/u-button/u-button")
                .then(t.bind(null, "d5d3"));
            },
            confirmModal: function () {
              return t
                .e("components/confirm-modal/confirm-modal")
                .then(t.bind(null, "4e5b"));
            },
            uCheckbox: function () {
              return t
                .e("uview-ui/components/u-checkbox/u-checkbox")
                .then(t.bind(null, "199f"));
            },
          },
          i = function () {
            var n = this,
              e =
                (n.$createElement,
                n._self._c,
                n.schematicSrc ? n.imgsrc(n.schematicSrc) : null);
            n._isMounted ||
              ((n.e0 = function (e) {
                n.schematicShow = !1;
              }),
              (n.e1 = function (e) {
                n.schematicShow = !1;
              })),
              (n.$mp.data = Object.assign({}, { $root: { m0: e } }));
          },
          c = [];
      },
      b3b7: function (n, e, t) {
        "use strict";
        t.r(e);
        var o = t("8e10"),
          i = t("0886");
        for (var c in i)
          ["default"].indexOf(c) < 0 &&
            (function (n) {
              t.d(e, n, function () {
                return i[n];
              });
            })(c);
        t("5769");
        var r = t("828b"),
          u = Object(r.a)(
            i.default,
            o.b,
            o.c,
            !1,
            null,
            "62daf21b",
            null,
            !1,
            o.a,
            void 0,
          );
        e.default = u.exports;
      },
      d3ed: function (n, e, t) {},
      ec14: function (n, e, t) {
        "use strict";
        (function (n, e) {
          var o = t("47a9");
          t("86d2"), o(t("3240"));
          var i = o(t("b3b7"));
          (n.__webpack_require_UNI_MP_PLUGIN__ = t), e(i.default);
        }).call(this, t("3223").default, t("df3c").createPage);
      },
    },
    [["ec14", "common/runtime", "common/vendor", "pageConfig/common/vendor"]],
  ]);
