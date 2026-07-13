(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pages/login/components/mp-weixin"],
  {
    "6dd0": function (t, n, e) {
      "use strict";
      var o = e("be79");
      e.n(o).a;
    },
    "89c0": function (t, n, e) {
      "use strict";
      (function (t) {
        var o = e("47a9");
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var i = o(e("7ca3")),
          r = e("f24f");
        function c(t, n) {
          var e = Object.keys(t);
          if (Object.getOwnPropertySymbols) {
            var o = Object.getOwnPropertySymbols(t);
            n &&
              (o = o.filter(function (n) {
                return Object.getOwnPropertyDescriptor(t, n).enumerable;
              })),
              e.push.apply(e, o);
          }
          return e;
        }
        function u(t) {
          for (var n = 1; n < arguments.length; n++) {
            var e = null != arguments[n] ? arguments[n] : {};
            n % 2
              ? c(Object(e), !0).forEach(function (n) {
                  (0, i.default)(t, n, e[n]);
                })
              : Object.getOwnPropertyDescriptors
                ? Object.defineProperties(
                    t,
                    Object.getOwnPropertyDescriptors(e),
                  )
                : c(Object(e)).forEach(function (n) {
                    Object.defineProperty(
                      t,
                      n,
                      Object.getOwnPropertyDescriptor(e, n),
                    );
                  });
          }
          return t;
        }
        var s = {
          props: {
            options: {
              type: Object,
              default: function () {
                return {};
              },
            },
          },
          data: function () {
            return {
              statusBarHeight: this.$u.sys().statusBarHeight,
              customStyle: { height: "88rpx", background: "#4C7AF2" },
            };
          },
          methods: {
            getphonenumber: function (t) {
              this.login(t.detail);
            },
            getUserInfo: function (n) {
              var e = this;
              t.getUserProfile({
                desc: "用于完善会员资料",
                success: function (t) {
                  e.login(t);
                },
                fail: function (t) {
                  e.login();
                },
              });
            },
            login: function (n) {
              var e = this;
              t.login({
                success: function (t) {
                  e.$store.dispatch("getStopInfo").then(function (t) {
                    e.$emit("setInfoToken", t);
                  });
                },
              });
            },
            loginBind: function (t) {
              var n = this;
              (0, r.thirdRelation2)(u(u({}, t), {}, { type: 3 })).then(
                function (t) {
                  n.$emit("getUserInfo", t.data.token);
                },
              );
            },
          },
        };
        n.default = s;
      }).call(this, e("df3c").default);
    },
    be79: function (t, n, e) {},
    c39b: function (t, n, e) {
      "use strict";
      e.d(n, "b", function () {
        return i;
      }),
        e.d(n, "c", function () {
          return r;
        }),
        e.d(n, "a", function () {
          return o;
        });
      var o = {
          uButton: function () {
            return e
              .e("uview-ui/components/u-button/u-button")
              .then(e.bind(null, "d5d3"));
          },
        },
        i = function () {
          this.$createElement;
          var t = (this._self._c, this.imgsrc("/static/imgs/mp_logo.png"));
          this.$mp.data = Object.assign({}, { $root: { m0: t } });
        },
        r = [];
    },
    c466: function (t, n, e) {
      "use strict";
      e.r(n);
      var o = e("c39b"),
        i = e("fd2e");
      for (var r in i)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return i[t];
            });
          })(r);
      e("6dd0");
      var c = e("828b"),
        u = Object(c.a)(
          i.default,
          o.b,
          o.c,
          !1,
          null,
          "43878180",
          null,
          !1,
          o.a,
          void 0,
        );
      n.default = u.exports;
    },
    fd2e: function (t, n, e) {
      "use strict";
      e.r(n);
      var o = e("89c0"),
        i = e.n(o);
      for (var r in o)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return o[t];
            });
          })(r);
      n.default = i.a;
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pages/login/components/mp-weixin-create-component",
    {
      "pages/login/components/mp-weixin-create-component": function (t, n, e) {
        e("df3c").createComponent(e("c466"));
      },
    },
    [["pages/login/components/mp-weixin-create-component"]],
  ]);
