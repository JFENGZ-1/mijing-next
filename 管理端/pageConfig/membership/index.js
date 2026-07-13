require("../common/vendor.js"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["pageConfig/membership/index"],
    {
      "54d3": function (t, n, e) {
        "use strict";
        (function (t) {
          var o = e("47a9");
          Object.defineProperty(n, "__esModule", { value: !0 }),
            (n.default = void 0);
          var u = o(e("7eb4")),
            i = o(e("ee10")),
            r = e("a994"),
            c = {
              data: function () {
                return { userList: [], isLoading: !0 };
              },
              components: {
                navigation: function () {
                  e.e("components/navigation/index")
                    .then(
                      function () {
                        return resolve(e("af9e"));
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
              },
              computed: {
                StatusBar: function () {
                  return this.$store.state.systemInfo.statusBarHeight;
                },
                CustomBar: function () {
                  var n = t.getMenuButtonBoundingClientRect();
                  return (
                    n.height +
                    2 * (n.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                    2
                  );
                },
              },
              methods: {
                goMember: function () {
                  t.switchTab({ url: "/pages/member/member" });
                },
                getList: function () {
                  var n = this;
                  return (0, i.default)(
                    u.default.mark(function e() {
                      return u.default.wrap(function (e) {
                        for (;;)
                          switch ((e.prev = e.next)) {
                            case 0:
                              return (
                                (e.next = 2),
                                (0, r.getuserFieldSetting)().then(function (e) {
                                  (n.isLoading = !1),
                                    200 == e.code
                                      ? (n.userList = e.configlist)
                                      : t.showToast({
                                          icon: "none",
                                          title: e.msg,
                                        });
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
                headleName: function (n, e) {
                  var o = this;
                  return (0, i.default)(
                    u.default.mark(function i() {
                      var c, a;
                      return u.default.wrap(function (u) {
                        for (;;)
                          switch ((u.prev = u.next)) {
                            case 0:
                              (c = { value: 0 == e ? 1 : 0, id: n }),
                                (a = 0 == e ? "添加成功" : "取消成功"),
                                (0, r.saveuserFieldSetting)(c).then(
                                  function (n) {
                                    200 == n.code
                                      ? (o.getList(),
                                        t.showToast({ icon: "none", title: a }))
                                      : t.showToast({
                                          icon: "none",
                                          title: n.msg,
                                        });
                                  },
                                );
                            case 3:
                            case "end":
                              return u.stop();
                          }
                      }, i);
                    }),
                  )();
                },
                change: function (n, e) {
                  var o = this;
                  return (0, i.default)(
                    u.default.mark(function i() {
                      var c, a, s;
                      return u.default.wrap(function (u) {
                        for (;;)
                          switch ((u.prev = u.next)) {
                            case 0:
                              return (
                                (c = { value: 1 == e ? 2 : 1, id: n }),
                                (u.next = 3),
                                (0, r.saveuserFieldSetting)(c)
                              );
                            case 3:
                              (a = u.sent),
                                (s = null),
                                200 == a.code &&
                                  ((s = "状态修改成功"),
                                  o.getList(),
                                  t.showToast({ icon: "none", title: s }));
                            case 6:
                            case "end":
                              return u.stop();
                          }
                      }, i);
                    }),
                  )();
                },
              },
              onLoad: function () {
                this.getList();
              },
            };
          n.default = c;
        }).call(this, e("df3c").default);
      },
      5933: function (t, n, e) {
        "use strict";
        e.r(n);
        var o = e("890d"),
          u = e("ff5c");
        for (var i in u)
          ["default"].indexOf(i) < 0 &&
            (function (t) {
              e.d(n, t, function () {
                return u[t];
              });
            })(i);
        e("7264");
        var r = e("828b"),
          c = Object(r.a)(
            u.default,
            o.b,
            o.c,
            !1,
            null,
            "0600a03e",
            null,
            !1,
            o.a,
            void 0,
          );
        n.default = c.exports;
      },
      7264: function (t, n, e) {
        "use strict";
        var o = e("c93c");
        e.n(o).a;
      },
      "890d": function (t, n, e) {
        "use strict";
        e.d(n, "b", function () {
          return u;
        }),
          e.d(n, "c", function () {
            return i;
          }),
          e.d(n, "a", function () {
            return o;
          });
        var o = {
            uIcon: function () {
              return e
                .e("uview-ui/components/u-icon/u-icon")
                .then(e.bind(null, "81af"));
            },
            uFormItem: function () {
              return Promise.all([
                e.e("common/vendor"),
                e.e("uview-ui/components/u-form-item/u-form-item"),
              ]).then(e.bind(null, "ec61"));
            },
            uSwitch: function () {
              return e
                .e("uview-ui/components/u-switch/u-switch")
                .then(e.bind(null, "a048"));
            },
            ffBottomLogo: function () {
              return e
                .e("components/ff-bottom-logo/ff-bottom-logo")
                .then(e.bind(null, "3111"));
            },
          },
          u = function () {
            this.$createElement;
            this._self._c;
          },
          i = [];
      },
      9983: function (t, n, e) {
        "use strict";
        (function (t, n) {
          var o = e("47a9");
          e("86d2"), o(e("3240"));
          var u = o(e("5933"));
          (t.__webpack_require_UNI_MP_PLUGIN__ = e), n(u.default);
        }).call(this, e("3223").default, e("df3c").createPage);
      },
      c93c: function (t, n, e) {},
      ff5c: function (t, n, e) {
        "use strict";
        e.r(n);
        var o = e("54d3"),
          u = e.n(o);
        for (var i in o)
          ["default"].indexOf(i) < 0 &&
            (function (t) {
              e.d(n, t, function () {
                return o[t];
              });
            })(i);
        n.default = u.a;
      },
    },
    [["9983", "common/runtime", "common/vendor", "pageConfig/common/vendor"]],
  ]);
